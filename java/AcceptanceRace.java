import java.net.URI;
import java.net.http.*;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.time.LocalDate;
import java.util.*;
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.regex.*;

/**
 * AcceptanceRace — arnés de concurrencia sobre la API real.
 *
 * Comprueba la garantía transaccional de sp_aceptar_propuesta: aunque N hilos
 * acepten la MISMA propuesta a la vez, sólo una aceptación debe prosperar y las
 * unidades reservadas no pueden duplicarse. Es la prueba de que el mercado no
 * sobrevende bajo carrera.
 *
 *   javac java/AcceptanceRace.java -d var/java
 *   java -cp var/java AcceptanceRace [http://127.0.0.1:8080] [hilos]
 *
 * Requiere el servidor en marcha con los datos de demo (php bin/install.php --seed).
 */
public final class AcceptanceRace {

    static String base;
    static final HttpClient HTTP = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(10)).build();
    static final String PASSWORD = "Alchemy2026";

    public static void main(String[] args) throws Exception {
        base = (args.length > 0 ? args[0] : "http://127.0.0.1:8080").replaceAll("/$", "");
        int threads = args.length > 1 ? Integer.parseInt(args[1]) : 8;

        System.out.println("Carrera de aceptación contra " + base + " · " + threads + " hilos");

        String venue = token("local@tastealchemy.test");
        String artisan = token("artesano@tastealchemy.test");

        // Elegir un lote publicable con stock.
        String lots = send("GET", "/api/v1/lots?per_page=50", null, null).body();
        long lotId = -1;
        double availableBefore = 0;
        for (String obj : splitObjects(lots)) {
            double coh = num(obj, "coherence_score");
            String status = str(obj, "status");
            double avail = num(obj, "units_available");
            if (coh >= 70 && avail >= 2 && (status.equals("en_reposo") || status.equals("embotellado"))) {
                lotId = (long) num(obj, "id");
                availableBefore = avail;
                System.out.println("Lote elegido: " + str(obj, "code") + " · disponibles " + (int) avail);
                break;
            }
        }
        if (lotId < 0) {
            System.err.println("FALLO: no hay un lote con coherencia ≥70 y stock. ¿Está cargada la demo?");
            System.exit(1);
        }

        // Petición del local.
        String reqBody = "{\"title\":\"Carrera de aceptación\",\"style\":\"Licor de fruta\","
                + "\"units_wanted\":2,\"territory\":\"Comunitat Valenciana\","
                + "\"target_date\":\"" + LocalDate.now().plusDays(60) + "\"}";
        long requestId = (long) num(send("POST", "/api/v1/requests", reqBody, venue).body(), "id");

        // Propuesta del artesano (2 unidades, venta).
        String propBody = "{\"lot_id\":" + lotId + ",\"units\":2,\"price_per_unit\":18.0,\"mode\":\"venta\"}";
        long proposalId = (long) num(send("POST", "/api/v1/requests/" + requestId + "/proposals", propBody, artisan).body(), "proposal_id");
        System.out.println("Propuesta " + proposalId + " creada; lanzando aceptaciones simultáneas…");

        // Carrera: N aceptaciones concurrentes de la misma propuesta.
        ExecutorService pool = Executors.newFixedThreadPool(threads);
        CountDownLatch gate = new CountDownLatch(1);
        AtomicInteger ok = new AtomicInteger();
        AtomicInteger rejected = new AtomicInteger();
        List<Future<?>> futures = new ArrayList<>();
        for (int i = 0; i < threads; i++) {
            futures.add(pool.submit(() -> {
                try {
                    gate.await();
                    Res res = send("POST", "/api/v1/proposals/" + proposalId + "/accept",
                            "{\"mode\":\"venta\"}", venue);
                    if (res.statusCode() == 201) ok.incrementAndGet();
                    else rejected.incrementAndGet();
                } catch (Exception e) {
                    rejected.incrementAndGet();
                }
            }));
        }
        gate.countDown(); // todas a la vez
        for (Future<?> f : futures) f.get(30, TimeUnit.SECONDS);
        pool.shutdown();

        // Invariante de unidades: sólo una aceptación reserva 2 unidades.
        String lotAfter = send("GET", "/api/v1/lots/" + lotId, null, null).body();
        double availableAfter = num(lotAfter, "units_available");
        double reserved = availableBefore - availableAfter;

        System.out.println("\nResultados:");
        System.out.println("  aceptaciones 201: " + ok.get() + " · rechazadas: " + rejected.get());
        System.out.printf("  unidades reservadas: %.0f (esperado 2)%n", reserved);

        boolean pass = ok.get() == 1 && Math.abs(reserved - 2) < 0.001;
        if (pass) {
            System.out.println("\nOK: exactamente una aceptación prosperó; no hay sobreventa.");
        } else {
            System.err.println("\nFALLO: la carrera produjo " + ok.get() + " aceptaciones y " + reserved + " unidades reservadas.");
            System.exit(1);
        }
    }

    // --- HTTP ---------------------------------------------------------------
    static final class Res {
        private final int statusCode;
        private final String body;
        Res(int statusCode, String body) { this.statusCode = statusCode; this.body = body; }
        int statusCode() { return statusCode; }
        String body() { return body; }
    }

    static Res send(String method, String path, String json, String token) throws Exception {
        HttpRequest.Builder b = HttpRequest.newBuilder(URI.create(base + path))
                .timeout(Duration.ofSeconds(30))
                .header("Accept", "application/json");
        if (token != null) b.header("Authorization", "Bearer " + token);
        if (json != null) {
            b.header("Content-Type", "application/json")
             .method(method, HttpRequest.BodyPublishers.ofString(json, StandardCharsets.UTF_8));
        } else {
            b.method(method, HttpRequest.BodyPublishers.noBody());
        }
        HttpResponse<String> r = HTTP.send(b.build(), HttpResponse.BodyHandlers.ofString());
        return new Res(r.statusCode(), r.body());
    }

    static String token(String email) throws Exception {
        Res r = send("POST", "/api/v1/auth/token",
                "{\"email\":\"" + email + "\",\"password\":\"" + PASSWORD + "\"}", null);
        if (r.statusCode() != 200) throw new IllegalStateException("token " + email + " → " + r.statusCode());
        return str(r.body(), "access_token");
    }

    // --- JSON mínimo (objetos planos) --------------------------------------
    static String str(String json, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*\"([^\"]*)\"").matcher(json);
        return m.find() ? m.group(1) : "";
    }

    static double num(String json, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*(-?[0-9]+(?:\\.[0-9]+)?)").matcher(json);
        return m.find() ? Double.parseDouble(m.group(1)) : 0;
    }

    /** Divide un array JSON de objetos planos en sus objetos. */
    static List<String> splitObjects(String arrayJson) {
        List<String> out = new ArrayList<>();
        int depth = 0, start = -1;
        boolean inString = false, escape = false;
        for (int i = 0; i < arrayJson.length(); i++) {
            char c = arrayJson.charAt(i);
            if (inString) {
                if (escape) escape = false;
                else if (c == '\\') escape = true;
                else if (c == '"') inString = false;
                continue;
            }
            if (c == '"') { inString = true; continue; }
            if (c == '{') { if (depth == 0) start = i; depth++; }
            else if (c == '}') { depth--; if (depth == 0 && start >= 0) { out.add(arrayJson.substring(start, i + 1)); start = -1; } }
        }
        return out;
    }
}