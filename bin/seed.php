<?php
declare(strict_types=1);

/**
 * bin/seed.php — datos de demostración del vertical F1–F10.
 *
 * Crea cuentas para los cinco roles, dos talleres, dos locales (uno verificado
 * y otro pendiente), recetas versionadas y forkeadas, lotes con lecturas físicas
 * repartidas en el tiempo, peticiones, propuestas, comisiones de venta y
 * consignación, facturas entre NIFs, una valoración verificada y una cata de
 * comisario. Es idempotente: si ya existe la cuenta artesano, no hace nada.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo se ejecuta por CLI.\n");
    exit(1);
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
require BASE_PATH . '/src/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Repository\UserRepository;
use App\Service\AccountService;
use App\Service\AuthService;
use App\Service\LotService;
use App\Service\MarketplaceService;
use App\Service\ModerationService;
use App\Service\RecipeService;

Config::load();
date_default_timezone_set('Europe/Madrid');

function seed_out(string $message): void
{
    echo $message, "\n";
}

/** NIF válido con dígito de control calculado. */
function nif_for(int $number): string
{
    $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
    return sprintf('%08d%s', $number, $letters[$number % 23]);
}

function actAs(int $userId): void
{
    $user = (new UserRepository())->find($userId);
    if ($user === null) {
        throw new RuntimeException('Usuario no encontrado: ' . $userId);
    }
    AuthService::setUser($user);
}

function days_ago(int $days): string
{
    return date('Y-m-d', strtotime("-{$days} days"));
}

$users = new UserRepository();
if ($users->findByEmail('artesano@tastealchemy.test') !== null) {
    seed_out('La base de datos ya tiene datos de demostración; no se hace nada.');
    seed_out('Para reinstalar desde cero: php bin/install.php --seed');
    exit(0);
}

$accounts = new AccountService();
$recipes = new RecipeService();
$lots = new LotService();
$market = new MarketplaceService();
$moderation = new ModerationService();
$password = 'Alchemy2026';

// ---------------------------------------------------------------------------
// 1. Cuentas. El personal se crea directo; talleres y locales pasan por el
//    mismo servicio que la web (validación de NIF, mayoría de edad, auditoría).
// ---------------------------------------------------------------------------
seed_out('· Cuentas y roles');
$staff = [
    ['admin@tastealchemy.test', 'Alma Rectora', 'admin', 10000001, '1980-02-11'],
    ['moderador@tastealchemy.test', 'Vela la Puerta', 'moderador', 10000002, '1983-07-24'],
    ['comisario@tastealchemy.test', 'Nariz de Oro', 'comisario', 10000003, '1978-11-02'],
];
$staffIds = [];
foreach ($staff as [$email, $name, $role, $nifNumber, $birth]) {
    $id = $users->create([
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'display_name' => $name,
        'birth_date' => $birth,
        'nif' => nif_for($nifNumber),
        'phone' => null,
    ]);
    $users->addRole($id, $role, $id);
    $staffIds[$role] = $id;
}

$artisanId = $accounts->registerArtisan([
    'email' => 'artesano@tastealchemy.test',
    'password' => $password,
    'password_confirmation' => $password,
    'display_name' => 'Carme Soler',
    'birth_date' => '1985-03-12',
    'nif' => nif_for(20000001),
    'studio_name' => 'Destilería La Cereza',
    'territory' => 'Comunitat Valenciana',
    'bio' => 'Cerezas de la Montaña de Alicante, maceraciones lentas y barricas de roble. Tres generaciones destilando paciencia.',
    'founded_year' => 1987,
]);
$obradorId = $accounts->registerArtisan([
    'email' => 'obrador@tastealchemy.test',
    'password' => $password,
    'password_confirmation' => $password,
    'display_name' => 'Vicent Estruch',
    'birth_date' => '1990-09-30',
    'nif' => nif_for(20000002),
    'studio_name' => 'Obrador del Azahar',
    'territory' => 'Comunitat Valenciana',
    'bio' => 'Cítricos de la Ribera y flores de azahar. Extracciones en frío, sin prisa.',
    'founded_year' => 2012,
]);
$venueId = $accounts->registerVenue([
    'email' => 'local@tastealchemy.test',
    'password' => $password,
    'password_confirmation' => $password,
    'display_name' => 'Mireia Bosch',
    'birth_date' => '1988-05-19',
    'nif' => nif_for(30000001),
    'venue_name' => 'Bar La Barrica',
    'territory' => 'Comunitat Valenciana',
    'license_number' => 'CV-REST-2019-0447',
    'license_type' => 'Licencia de restauración',
    'address' => 'Carrer de la Mar, 14 · València',
    'document_ref' => 'Registro autonómico CV/2019/0447',
]);
$hotelId = $accounts->registerVenue([
    'email' => 'hotel@tastealchemy.test',
    'password' => $password,
    'password_confirmation' => $password,
    'display_name' => 'Rosa Peris',
    'birth_date' => '1975-12-03',
    'nif' => nif_for(30000002),
    'venue_name' => 'Hotel Miramar',
    'territory' => 'Comunitat Valenciana',
    'license_number' => 'CV-HOT-2021-1103',
    'license_type' => 'Licencia de restauración',
    'address' => 'Passeig del Port, 3 · Alacant',
    'document_ref' => 'Expediente CV/HOT/2021/1103',
]);

// El moderador verifica La Barrica; el Hotel Miramar queda en cola.
actAs($staffIds['moderador']);
$verification = Database::first(
    'SELECT id FROM verification_requests WHERE venue_id = ? ORDER BY id DESC LIMIT 1',
    [$venueId]
);
$moderation->approveVerification($staffIds['moderador'], (int) $verification['id'], 'Comprobado en el registro autonómico CV/2019/0447.');
seed_out('  7 cuentas · licencia de La Barrica verificada · Hotel Miramar pendiente');

// ---------------------------------------------------------------------------
// 2. Recetas versionadas y forkeadas.
// ---------------------------------------------------------------------------
seed_out('· Recetas');
actAs($artisanId);
$cerezaInput = [
    'base' => 'cereza',
    'mode' => 'ingredientes',
    'base_abv_pct' => 40,
    'vodka_ml' => 1000,
    'ingredient_g' => 1000,
    'desired_volume_ml' => 2000,
    'extraction' => 'hybrid',
    'sugar_ratio' => 0.40,
    'water_ratio' => 0.50,
    'rest_days' => 21,
    'syrup' => 'homemade',
];
$recipeCereza = $recipes->create($artisanId, $cerezaInput + [
    'title' => 'Cereza de Alcoi · maceración lenta',
    'style' => 'Licor de fruta',
    'description' => 'Cereza ácida de montaña, semana híbrida de extracción y tres semanas de reposo. Granate profundo, acidez viva y un final tánico.',
    'tags' => 'cereza, montaña, roble',
]);
$recipes->addVersion($recipeCereza, $artisanId, $cerezaInput + [
    'rest_days' => 30,
    'changelog' => 'Reposo ampliado a 30 días: el tanino se redondea y el color se estabiliza.',
]);

$recipeHibisco = $recipes->create($artisanId, [
    'base' => 'hibisco',
    'mode' => 'ingredientes',
    'base_abv_pct' => 40,
    'vodka_ml' => 700,
    'ingredient_g' => 60,
    'desired_volume_ml' => 1400,
    'extraction' => 'limoncello',
    'sugar_ratio' => 0.38,
    'water_ratio' => 0.50,
    'rest_days' => 14,
    'title' => 'Hibisco de la Serra',
    'style' => 'Licor de flor',
    'description' => 'Cálices de hibisco en frío durante una semana. Carmesí intenso, acidez de arándano y un final limpio.',
    'tags' => 'hibisco, flor, frío',
]);

actAs($obradorId);
$recipeLimon = $recipes->create($obradorId, [
    'base' => 'cereza',
    'mode' => 'ingredientes',
    'base_abv_pct' => 40,
    'vodka_ml' => 1000,
    'ingredient_g' => 700,
    'desired_volume_ml' => 1800,
    'extraction' => 'limoncello',
    'sugar_ratio' => 0.42,
    'water_ratio' => 0.50,
    'rest_days' => 14,
    'title' => 'Limoncello del Azahar',
    'style' => 'Licor de fruta',
    'description' => 'Limón de la Ribera con un toque de azahar. Siete días en frío, agitado a diario, y almíbar casero con la piel.',
    'tags' => 'limón, azahar, limoncello',
]);
$forkId = $recipes->fork($recipeCereza, $obradorId, 'Cereza del Obrador · fork con reposo largo');
seed_out('  4 recetas · 1 versión nueva · 1 fork');

// ---------------------------------------------------------------------------
// 3. Lotes con lecturas físicas repartidas en el tiempo.
//    La trayectoria sigue la física del veredicto: evaporación ~2,5 %/año y
//    pérdida de graduación ~0,35 puntos/año.
// ---------------------------------------------------------------------------
seed_out('· Lotes y lecturas');
$versionCerezaV1 = (int) Database::value(
    'SELECT id FROM recipe_versions WHERE recipe_id = ? ORDER BY version_no ASC LIMIT 1',
    [$recipeCereza]
);
$versionCerezaV2 = (int) Database::value(
    'SELECT id FROM recipe_versions WHERE recipe_id = ? ORDER BY version_no DESC LIMIT 1',
    [$recipeCereza]
);
$versionLimon = (int) Database::value(
    'SELECT id FROM recipe_versions WHERE recipe_id = ? ORDER BY version_no DESC LIMIT 1',
    [$recipeLimon]
);

/**
 * Registra un lote y sus lecturas a lo largo del periodo.
 * @param array<int, int> $readingDays días desde el inicio en que se mide
 */
function seed_lot(LotService $lots, int $artisanId, array $data, array $readingDays, float $volume0, float $abv0): int
{
    actAs($artisanId);
    $lotId = $lots->create($artisanId, $data);
    $start = strtotime($data['started_at']);
    foreach ($readingDays as $day) {
        if ($day === 0) {
            continue; // la primera lectura la crea el propio alta
        }
        $years = $day / 365.0;
        $lots->addReading($artisanId, $lotId, [
            'volume_l' => round($volume0 * (1 - 0.025 * $years), 3),
            'abv' => round($abv0 - 0.35 * $years, 2),
            'taken_at' => date('Y-m-d', $start + $day * 86400),
            'temperature_c' => 18.0,
        ]);
    }
    return $lotId;
}

/**
 * Avanza el lote por la cadena de estados hasta el objetivo. Las lecturas
 * antiguas ya promocionan el lote a en_reposo por disparador de procedimiento,
 * así que se salta los pasos ya alcanzados.
 */
function advance_lot(LotService $lots, int $artisanId, int $lotId, string $target): void
{
    $chain = ['en_maceracion', 'en_reposo', 'embotellado', 'conservado'];
    $current = (string) Database::value('SELECT status FROM lots WHERE id = ?', [$lotId]);
    $from = array_search($current, $chain, true);
    $to = array_search($target, $chain, true);
    if ($from === false || $to === false) {
        return;
    }
    for ($i = $from + 1; $i <= $to; $i++) {
        $lots->changeStatus($artisanId, $lotId, $chain[$i]);
    }
}

$lotA = seed_lot($lots, $artisanId, [
    'recipe_version_id' => $versionCerezaV1,
    'code' => 'LOT-2025-0007',
    'barrel_ref' => 'Roble francés 225 L · barrica 7',
    'territory' => 'Comunitat Valenciana',
    'initial_volume_l' => 20.0,
    'initial_abv' => 24.0,
    'started_at' => days_ago(400),
    'status' => 'en_maceracion',
    'units_total' => 60,
    'unit_ml' => 700,
    'price_per_unit' => 18.00,
    'notes' => 'Cereza de la partida alta, reposo en barrica desde el trasiego.',
], [80, 160, 240, 320, 395], 20.0, 24.0);
advance_lot($lots, $artisanId, $lotA, 'embotellado');
$lots->publishListing($artisanId, $lotA, [
    'title' => 'Cereza de Alcoi · barrica 7',
    'description' => '400 días de barrica con lecturas trimestrales. Granate profundo, 23,6 % vol.',
    'units_available' => 40,
    'price_per_unit' => 18.00,
]);

$lotB = seed_lot($lots, $artisanId, [
    'recipe_version_id' => $versionCerezaV2,
    'code' => 'LOT-2026-0003',
    'barrel_ref' => 'Roble americano 200 L · barrica 3',
    'territory' => 'Comunitat Valenciana',
    'initial_volume_l' => 10.0,
    'initial_abv' => 26.0,
    'started_at' => days_ago(200),
    'status' => 'en_maceracion',
    'units_total' => 30,
    'unit_ml' => 700,
    'price_per_unit' => 21.00,
    'notes' => 'Versión de reposo largo; pendiente de embotellar.',
], [60, 120, 195], 10.0, 26.0);
advance_lot($lots, $artisanId, $lotB, 'en_reposo');
$lots->publishListing($artisanId, $lotB, [
    'title' => 'Cereza de Alcoi · reposo largo',
    'description' => 'La versión v2 con 30 días de reposo. Más redonda, mismo carácter.',
    'units_available' => 24,
    'price_per_unit' => 21.00,
]);

$lotC = seed_lot($lots, $obradorId, [
    'recipe_version_id' => $versionLimon,
    'code' => 'LOT-2026-0011',
    'territory' => 'Comunitat Valenciana',
    'initial_volume_l' => 8.0,
    'initial_abv' => 30.0,
    'started_at' => days_ago(120),
    'status' => 'en_maceracion',
    'units_total' => 24,
    'unit_ml' => 500,
    'price_per_unit' => 16.50,
    'notes' => 'Limón de la Ribera, extracción en frío sin pérdida.',
], [30, 60, 90, 115], 8.0, 30.0);
advance_lot($lots, $obradorId, $lotC, 'embotellado');
$lots->publishListing($obradorId, $lotC, [
    'title' => 'Limoncello del Azahar · primera partida',
    'description' => 'Formato de 500 ml para terraza. Cítrico limpio, dulzor medido.',
    'units_available' => 20,
    'price_per_unit' => 16.50,
]);

$lotD = seed_lot($lots, $artisanId, [
    'recipe_version_id' => $versionCerezaV2,
    'code' => 'LOT-2026-0019',
    'territory' => 'Comunitat Valenciana',
    'initial_volume_l' => 6.0,
    'initial_abv' => 25.0,
    'started_at' => days_ago(600),
    'status' => 'en_maceracion',
    'units_total' => 18,
    'unit_ml' => 700,
    'price_per_unit' => 19.00,
    'notes' => 'Partida sin lecturas periódicas: el silencio resta coherencia.',
], [], 6.0, 25.0);

$scores = Database::select('SELECT code, coherence_score FROM lots ORDER BY id');
foreach ($scores as $row) {
    seed_out(sprintf('  %s · coherencia %d/100', $row['code'], $row['coherence_score']));
}

// ---------------------------------------------------------------------------
// 4. Peticiones, propuestas y comisiones (venta + consignación).
// ---------------------------------------------------------------------------
seed_out('· Mercado');
actAs($venueId);
$request1 = $market->createRequest($venueId, [
    'title' => 'Licor de cereza para carta de invierno',
    'style' => 'Licor de fruta',
    'description' => 'Buscamos un licor de cereza con cuerpo para la carta de invierno: copa corta, postre de chocolate y sobremesa. Queremos exclusividad territorial en València.',
    'territory' => 'Comunitat Valenciana',
    'units_wanted' => 24,
    'abv_min' => 18,
    'abv_max' => 32,
    'target_date' => date('Y-m-d', strtotime('+45 days')),
    'exclusivity_requested' => 1,
]);
$request2 = $market->createRequest($venueId, [
    'title' => 'Limoncello para terraza de verano',
    'style' => 'Licor de fruta',
    'description' => 'Cítrico limpio y dulzor medido para servir muy frío en terraza. Formato pequeño, rotación alta.',
    'territory' => 'Comunitat Valenciana',
    'units_wanted' => 12,
    'abv_min' => 25,
    'abv_max' => 34,
    'target_date' => date('Y-m-d', strtotime('+60 days')),
]);
$request3 = $market->createRequest($venueId, [
    'title' => 'Digestivo de hierbas para sobremesa',
    'style' => 'Licor de hierbas',
    'description' => 'Perfil amable, amargor bajo, final fresco. Aún sin propuestas: el mercado responde cuando quiere.',
    'territory' => 'Comunitat Valenciana',
    'units_wanted' => 18,
    'abv_min' => 20,
    'abv_max' => 30,
    'target_date' => date('Y-m-d', strtotime('+75 days')),
]);

actAs($artisanId);
$proposalA = $market->propose($artisanId, $request1, [
    'lot_id' => $lotA,
    'units' => 24,
    'price_per_unit' => 18.00,
    'mode' => 'ambas',
    'exclusivity' => 1,
    'exclusivity_fee' => 60.00,
    'message' => 'Barrica 7, 400 días de lecturas trimestrales. Exclusividad para València mientras dure la carta.',
]);
$proposalB = $market->propose($artisanId, $request3, [
    'lot_id' => $lotB,
    'units' => 18,
    'price_per_unit' => 21.00,
    'mode' => 'ambas',
    'message' => 'No es de hierbas, pero el reposo largo le da un perfil de sobremesa que encaja.',
]);
actAs($obradorId);
$proposalC = $market->propose($obradorId, $request1, [
    'lot_id' => $lotC,
    'units' => 24,
    'price_per_unit' => 16.50,
    'mode' => 'venta',
    'message' => 'Alternativa cítrica por si la carta admite un giro.',
]);
$proposalLimon = $market->propose($obradorId, $request2, [
    'lot_id' => $lotC,
    'units' => 12,
    'price_per_unit' => 16.50,
    'mode' => 'consignacion',
    'message' => 'Doce unidades en consignación a 90 días; lo no servido se devuelve.',
]);

// Venta: aceptación → factura → entrega confirmada → completada.
actAs($venueId);
$commission1 = $market->accept($venueId, $proposalA, 'venta');
$market->completeDelivery($venueId, $commission1);
// Retrocedemos el cierre 5 días para poder demostrar la valoración de 72 h.
Database::execute('UPDATE commissions SET completed_at = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('-5 days')), $commission1]);

// Consignación: aceptación → cierre con vendidas/devueltas → factura.
$commission2 = $market->accept($venueId, $proposalLimon, 'consignacion');
$consignment2 = Database::first('SELECT id FROM consignments WHERE commission_id = ?', [$commission2]);
$market->closeConsignment($venueId, (int) $consignment2['id'], 10, 2);
Database::execute('UPDATE commissions SET completed_at = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('-4 days')), $commission2]);

// Valoración verificada del local (72 h cumplidas) y cata del comisario.
$reviewId = $market->submitReview($venueId, $commission1, [
    'body' => 'Servido en copa corta con chocolate negro: la acidez de la cereza limpia el cacao y el final tánico pide otro sorbo. A las 72 horas sigue siendo el mejor licor de la carta.',
    'score_aroma' => 9,
    'score_texture' => 8,
    'score_balance' => 9,
    'score_finish' => 8,
]);
actAs($staffIds['comisario']);
$tastingId = $market->submitTasting($staffIds['comisario'], $lotA, [
    'body' => 'Granate profundo con ribete teja. Nariz de cereza madura y roble discreto; en boca entra dulce y termina seco, con un tanino que ordena el postre. Un lote que sabe a tiempo bien administrado.',
    'score_aroma' => 9,
    'score_texture' => 9,
    'score_balance' => 8,
    'score_finish' => 9,
]);

// Un reporte para la cola de moderación.
actAs($hotelId);
$market->report($hotelId, [
    'entity_type' => 'listing',
    'entity_id' => (int) Database::value('SELECT id FROM listings WHERE lot_id = ?', [$lotA]),
    'reason' => 'Precio fuera de mercado',
    'details' => 'El precio por unidad parece alto para el formato; pido revisión.',
]);
seed_out('  3 peticiones · 4 propuestas · 2 comisiones · 2 facturas · 1 valoración · 1 cata · 1 reporte');

// ---------------------------------------------------------------------------
// 5. Resumen.
// ---------------------------------------------------------------------------
$metrics = json_data(Database::call('sp_metricas_plataforma', [], ['metrics'])['metrics'] ?? null);
seed_out(sprintf(
    '  Plataforma: %d cuentas · %d recetas · %d lotes · %d comisiones (%d completadas) · GMV %s €',
    $metrics['users'] ?? 0,
    $metrics['recipes'] ?? 0,
    $metrics['lots'] ?? 0,
    $metrics['commissions'] ?? 0,
    $metrics['commissions_completed'] ?? 0,
    number_format((float) ($metrics['gmv'] ?? 0), 2, ',', '.')
));
seed_out('');
seed_out('Demo cargada. Cuentas (contraseña para todas: ' . $password . '):');
seed_out('  artesano@tastealchemy.test   · taller Destilería La Cereza');
seed_out('  obrador@tastealchemy.test    · taller Obrador del Azahar');
seed_out('  local@tastealchemy.test      · Bar La Barrica (verificado)');
seed_out('  hotel@tastealchemy.test      · Hotel Miramar (licencia pendiente)');
seed_out('  comisario@tastealchemy.test  · catas ponderadas');
seed_out('  moderador@tastealchemy.test  · verificaciones y reportes');
seed_out('  admin@tastealchemy.test      · parámetros y roles');