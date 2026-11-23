import java.nio.file.*;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.*;

/**
 * CoherenceVerifier — verificador de coherencia en Java puro.
 *
 * Réplica de src/Calculation/CoherenceCalculator.php y del procedimiento
 * sp_coherencia_lote: comprueba que la física (evaporación, trayectoria de
 * graduación, cadencia y declaración) da el mismo veredicto en un tercer
 * lenguaje, sin base de datos.
 *
 * Uso:
 *   javac java/CoherenceVerifier.java -d var/java
 *   java -cp var/java CoherenceVerifier --demo
 *   java -cp var/java CoherenceVerifier 20 24 23.65 2025-01-01 lecturas.csv
 *
 * El CSV tiene líneas "fecha,volumen_l,abv" (cabecera opcional).
 */
public final class CoherenceVerifier {

    static final double EVAP_K = 0.025;
    static final double ABV_K = 0.35;
    static final double EVAP_TOL = 0.15;
    static final double ABV_TOL = 4.0;
    static final double DECL_TOL = 0.5;
    static final double CADENCE_PER_YEAR = 4.0;
    static final double W_EVAP = 0.35, W_ABV = 0.25, W_CAD = 0.20, W_DECL = 0.20;

    static final class Reading {
        private final LocalDate date;
        private final double volume;
        private final double abv;
        Reading(LocalDate date, double volume, double abv) {
            this.date = date; this.volume = volume; this.abv = abv;
        }
        LocalDate date() { return date; }
        double volume() { return volume; }
        double abv() { return abv; }
    }

    public static void main(String[] args) throws Exception {
        if (args.length == 0 || args[0].equals("--demo")) {
            demo();
            return;
        }
        if (args.length < 5) {
            System.err.println("Uso: CoherenceVerifier <v0_l> <abv0> <abv_declarado> <inicio> <lecturas.csv>");
            System.exit(2);
        }
        double v0 = Double.parseDouble(args[0]);
        double abv0 = Double.parseDouble(args[1]);
        double declared = Double.parseDouble(args[2]);
        LocalDate start = LocalDate.parse(args[3]);
        List<Reading> readings = readCsv(args[4]);
        int score = compute(v0, abv0, declared, start, readings, true);
        System.out.println("score=" + score);
    }

    static List<Reading> readCsv(String path) throws Exception {
        List<Reading> out = new ArrayList<>();
        for (String line : Files.readAllLines(Path.of(path))) {
            line = line.trim();
            if (line.isEmpty() || line.toLowerCase().startsWith("taken") || line.startsWith("#")) continue;
            String[] p = line.split(",");
            out.add(new Reading(LocalDate.parse(p[0].trim()), Double.parseDouble(p[1].trim()), Double.parseDouble(p[2].trim())));
        }
        out.sort(Comparator.comparing(Reading::date));
        return out;
    }

    static double clampScore(double factor) {
        return Math.max(0, Math.min(100, Math.round(100.0 * factor * 100.0) / 100.0));
    }

    static int compute(double v0, double abv0, double declared, LocalDate start, List<Reading> readings, boolean verbose) {
        if (readings.isEmpty()) {
            if (verbose) System.out.println("sin_datos: el lote no tiene lecturas.");
            return 0;
        }
        readings.sort(Comparator.comparing(Reading::date));
        Reading last = readings.get(readings.size() - 1);
        long spanDays = ChronoUnit.DAYS.between(readings.get(0).date(), last.date());
        long ageDays = ChronoUnit.DAYS.between(start, last.date());
        double years = Math.max(0, ageDays / 365.25);

        // A) Evaporación
        double expectedVolume = v0 * (1 - EVAP_K * years);
        if (expectedVolume <= 0) expectedVolume = 0.001;
        double evapDev = Math.abs(last.volume() - expectedVolume) / expectedVolume;
        double evapScore = clampScore(1 - evapDev / EVAP_TOL);

        // B) Trayectoria de graduación
        double expectedAbv = Math.max(0, abv0 - ABV_K * years);
        double abvDev = Math.abs(last.abv() - expectedAbv);
        double abvScore = clampScore(1 - abvDev / ABV_TOL);
        int increases = 0;
        for (int i = 1; i < readings.size(); i++) {
            if (readings.get(i).abv() > readings.get(i - 1).abv() + 0.5) increases++;
        }
        if (increases > 0) abvScore = Math.max(0, abvScore - Math.min(30, increases * 5.0));

        // C) Cadencia
        long maxGap = 0;
        for (int i = 1; i < readings.size(); i++) {
            maxGap = Math.max(maxGap, ChronoUnit.DAYS.between(readings.get(i - 1).date(), readings.get(i).date()));
        }
        int expectedReadings = Math.max(1, (int) Math.ceil(years * CADENCE_PER_YEAR));
        double countFactor = Math.min(1.0, readings.size() / (double) expectedReadings);
        double gapFactor = spanDays <= 0 ? 0.5 : Math.max(0, 1 - maxGap / (double) (spanDays + 1));
        double cadenceScore = Math.round(100.0 * (0.6 * countFactor + 0.4 * gapFactor) * 100.0) / 100.0;

        // D) Declaración
        double declDev = Math.abs(declared - last.abv());
        double declScore = clampScore(1 - declDev / DECL_TOL);

        int total = (int) Math.round(W_EVAP * evapScore + W_ABV * abvScore + W_CAD * cadenceScore + W_DECL * declScore);
        if (verbose) {
            System.out.printf(Locale.ROOT, "años=%.3f lecturas=%d hueco_max=%d%n", years, readings.size(), maxGap);
            System.out.printf(Locale.ROOT, "evaporación=%.0f abv=%.0f cadencia=%.0f declaración=%.0f%n",
                    evapScore, abvScore, cadenceScore, declScore);
            System.out.println("veredicto=" + verdict(total));
        }
        return total;
    }

    static String verdict(int score) {
        if (score >= 85) return "solido";
        if (score >= 70) return "plausible";
        if (score >= 45) return "dudoso";
        return "incoherente";
    }

    /** Genera lecturas que siguen la física y comprueba que puntúan alto. */
    static void demo() {
        LocalDate start = LocalDate.of(2025, 1, 1);
        List<Reading> readings = new ArrayList<>();
        int[] days = {0, 90, 180, 270, 360};
        double lastAbv = 0;
        for (int d : days) {
            double years = d / 365.25;
            double vol = Math.round(20.0 * (1 - 0.025 * years) * 1000.0) / 1000.0;
            double abv = Math.round((24.0 - 0.35 * years) * 100.0) / 100.0;
            readings.add(new Reading(start.plusDays(d), vol, abv));
            lastAbv = abv;
        }
        System.out.println("Demo: lote bien medido (5 lecturas, 1 año)");
        int score = compute(20.0, 24.0, lastAbv, start, readings, true);
        System.out.println("score=" + score);
        if (score < 85) {
            System.err.println("FALLO: se esperaba un lote sólido (≥85).");
            System.exit(1);
        }
        System.out.println("OK: la física de Java coincide con el veredicto esperado.");
    }
}