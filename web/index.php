<?php
// DB-tilkobling (bruker oppsettet du hadde i db/init.sh)
$host = 'db';
$db   = 'varehusdb';
$user = 'appuser';
$pass = 'apppass';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  $kundeCount = (int)$pdo->query("SELECT COUNT(*) FROM kunde")->fetchColumn();

  $ordrer = $pdo->query("
    SELECT o.OrdreNr, o.OrdreDato, k.Fornavn, k.Etternavn
    FROM ordre o
    JOIN kunde k ON k.KNr = o.KNr
    ORDER BY o.OrdreDato DESC, o.OrdreNr DESC
    LIMIT 10
  ")->fetchAll();

  $toppVarer = $pdo->query("
    SELECT v.VNr, v.VareNavn, SUM(ol.Antall) AS Solgt
    FROM ordrelinje ol
    JOIN vare v ON v.VNr = ol.VNr
    GROUP BY v.VNr, v.VareNavn
    ORDER BY Solgt DESC
    LIMIT 10
  ")->fetchAll();

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h1>DB-tilkobling feilet</h1><pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "</pre>";
  exit;
}
?>
<!doctype html>
<html lang="no">
<meta charset="utf-8">
<title>VarehusDB – Oversikt</title>
<style>
  body { font-family: system-ui, Arial, sans-serif; margin: 2rem; background:#f5f7fb; }
  h1 { margin-bottom: .25rem; }
  .grid { display:grid; gap:1.25rem; grid-template-columns: 1fr 1fr; }
  .card { background:#fff; border-radius:12px; padding:1rem 1.25rem; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
  table { border-collapse: collapse; width: 100%; }
  th, td { padding: .6rem .75rem; border-bottom: 1px solid #eee; text-align:left; }
  th { background:#fafafa; }
  small{color:#667; }
</style>
<body>
  <h1>VarehusDB</h1>
  <small>Web ⇄ DB koblet (PDO MySQL). Databasen leses fra aktiv MariaDB-container.</small>

  <div class="grid" style="margin-top:1rem">
    <div class="card">
      <h2>Status</h2>
      <p>Antall kunder: <b><?= htmlspecialchars($kundeCount) ?></b></p>
      <p>Database: <code><?= htmlspecialchars($db) ?></code> på host <code><?= htmlspecialchars($host) ?></code></p>
    </div>

    <div class="card">
      <h2>Siste 10 ordre</h2>
      <table>
        <thead><tr><th>OrdreNr</th><th>Dato</th><th>Kunde</th></tr></thead>
        <tbody>
          <?php foreach ($ordrer as $r): ?>
            <tr>
              <td><?= (int)$r['OrdreNr'] ?></td>
              <td><?= htmlspecialchars($r['OrdreDato']) ?></td>
              <td><?= htmlspecialchars($r['Fornavn'] . ' ' . $r['Etternavn']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
      <h2>Topp 10 varer (solgt antall)</h2>
      <table>
        <thead><tr><th>VNr</th><th>Varenavn</th><th>Solgt</th></tr></thead>
        <tbody>
          <?php foreach ($toppVarer as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['VNr']) ?></td>
              <td><?= htmlspecialchars($v['VareNavn']) ?></td>
              <td><?= (int)$v['Solgt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
