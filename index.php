<?php
session_start();

// ====================== LOGIN ======================
$USERNAME = "admin";
$PASSWORD = "password123";

if(isset($_POST['login'])){
    if($_POST['username']==$USERNAME && $_POST['password']==$PASSWORD){
        $_SESSION['logged_in']=true;
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } else {
        $error="Username atau password salah";
    }
}

if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

if(!isset($_SESSION['logged_in'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login - DOKU.Promo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex justify-content-center align-items-center" style="height:100vh;">
  <form method="post" class="p-4 bg-black rounded-4" style="width:300px;">
    <h4 class="mb-3 text-center text-light">Sign In</h4>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
    <button type="submit" name="login" class="btn btn-danger riunded-3 w-100">Sign In</button>
  </form>
</body>
</html>
<?php exit; endif; ?>

<?php
// ====================== MATOMO ======================
date_default_timezone_set('Asia/Jakarta'); // pakai timezone Indonesia

$matomoUrl = "https://github.com/dokupromo/analy"; 
$siteId    = 1;
$token     = "aadbf3a52da9d94ac70a5d1a3fe6b2a4";

function fetchMatomo($matomoUrl, $method, $siteId, $period, $date, $token, $extra = []) {
    $url = $matomoUrl . "?module=API&method=$method&idSite=$siteId&period=$period&date=$date&format=JSON";
    if(!empty($extra)) {
        foreach($extra as $k=>$v) $url.="&$k=$v";
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['token_auth' => $token]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ----------------- TOTAL VISITS ALL TIME -----------------
$dataVisitsAll = fetchMatomo($matomoUrl, "VisitsSummary.getVisits", $siteId, "range", "last1825", $token);
$totalVisitsAll = 0;
if(is_array($dataVisitsAll)){
    foreach($dataVisitsAll as $v){
        $totalVisitsAll += intval($v);
    }
}

// ----------------- TOTAL UNIQUE VISITORS ALL TIME (Indonesia TZ) -----------------
$dataUniqueAll = fetchMatomo($matomoUrl, "VisitsSummary.getUniqueVisitors", $siteId, "range", "last1825", $token);
$totalUniqueAll = 0;
if (is_array($dataUniqueAll)) {
    foreach ($dataUniqueAll as $v) {
        $totalUniqueAll += intval($v);
    }
}

// ----------------- TOTAL VISITS THIS MONTH -----------------
$dataVisitsMonth = fetchMatomo($matomoUrl, "VisitsSummary.getVisits", $siteId, "month", "today", $token);
$totalVisitsMonth = 0;
if(is_array($dataVisitsMonth)){
    foreach($dataVisitsMonth as $v){
        $totalVisitsMonth += intval($v);
    }
}

// ----------------- TOTAL UNIQUE VISITORS THIS MONTH -----------------
$dataUniqueMonth = fetchMatomo($matomoUrl, "VisitsSummary.getUniqueVisitors", $siteId, "month", "today", $token);
$totalUniqueMonth = 0;
if(is_array($dataUniqueMonth)){
    foreach($dataUniqueMonth as $v){
        $totalUniqueMonth += intval($v);
    }
}

// ----------------- LIVE DATA -----------------
$liveData = fetchMatomo(
    $matomoUrl,
    "Live.getLastVisitsDetails",
    $siteId,
    "range",
    "last30", // ubah dari last7 ke last30
    $token,
    [
        "filter_limit" => 1000 // ambil sampai 1000 data
    ]
);




// ----------------- TOTAL VISITS TODAY -----------------
$dataVisitsToday = fetchMatomo($matomoUrl, "VisitsSummary.getVisits", $siteId, "day", "today", $token);
$totalVisitsToday = 0;
if(is_array($dataVisitsToday)){
    foreach($dataVisitsToday as $v) $totalVisitsToday += intval($v);
}

// ----------------- REALTIME VISITOR COUNTER -----------------
$realtime = fetchMatomo($matomoUrl, "Live.getCounters", $siteId, "day", "today", $token, ["lastMinutes"=>30]);
$visitorsNow = 0;
if(is_array($realtime) && !empty($realtime[0]['visitors'])){
    $visitorsNow = $realtime[0]['visitors'];
}


$visitorsNow = 0;
if (is_array($realtime) && !empty($realtime[0]['visitors'])) {
    $visitorsNow = $realtime[0]['visitors'];
}

// ----------------- Struktur per user -----------------
$users = [];
foreach ($liveData as $visit) {
    $userId = $visit['visitorId'] ?? '-';
    if(!isset($users[$userId])) $users[$userId]=['sources'=>[],'landings'=>[],'journeys'=>[],'exits'=>[],'sessions'=>[]];

    // Source
    $source = $visit['referrerName'] ?? '';
    if(empty($source) && !empty($visit['referrerUrl'])) $source=parse_url($visit['referrerUrl'],PHP_URL_HOST) ?: '-';
    if(empty($source) && !empty($visit['referrerType'])) $source=ucfirst($visit['referrerType']);
    $users[$userId]['sources'][] = $source ?: '-';

    // Landing
    $landingTitle=$landingPath='-';
    if(!empty($visit['entryPageTitle']) || !empty($visit['entryPageUrl'])){
        $landingTitle=$visit['entryPageTitle'] ?? $visit['entryPageUrl'];
        $landingPath=parse_url($visit['entryPageUrl'] ?? '',PHP_URL_PATH);
    } elseif(!empty($visit['actionDetails'][0])){
        $landingTitle=$visit['actionDetails'][0]['pageTitle'] ?? $visit['actionDetails'][0]['url'];
        $landingPath=parse_url($visit['actionDetails'][0]['url'] ?? '',PHP_URL_PATH);
    }
    $users[$userId]['landings'][]=['title'=>$landingTitle,'path'=>$landingPath];

    // Exit per session unik
    $exitTitle=$exitPath='-';
    if(!empty($visit['exitPageTitle']) || !empty($visit['exitPageUrl'])){
        $exitTitle=$visit['exitPageTitle'] ?? $visit['exitPageUrl'];
        $exitPath=parse_url($visit['exitPageUrl'] ?? '',PHP_URL_PATH);
    } elseif(!empty($visit['actionDetails'])){
        $lastAction=end($visit['actionDetails']);
        $exitTitle=$lastAction['pageTitle'] ?? $lastAction['url'] ?? '-';
        $exitPath=parse_url($lastAction['url'] ?? '',PHP_URL_PATH);
    }
    $sessionId=$visit['idVisit'] ?? '-';
    $users[$userId]['exits'][$sessionId]=['title'=>$exitTitle,'path'=>$exitPath];

    // Sessions
    $users[$userId]['sessions'][]=$sessionId;

    // Journey per sesi
  // Journey per sesi
$paths = [];
if (!empty($visit['actionDetails'])) {
    $seen = []; // simpan path yang sudah muncul biar unik

    foreach ($visit['actionDetails'] as $a) {
        // Ambil judul & path
        $title = $a['pageTitle'] ?? $a['url'];
        $path  = parse_url($a['url'] ?? '', PHP_URL_PATH);

        // Filter judul kosong, Show All, Show Less
        if (
            !empty(trim($title)) &&
            stripos($title, "Show All") === false &&
            stripos($title, "Show Less") === false
        ) {
            // Hanya masukkan kalau path belum pernah muncul di sesi ini
            $key = md5($title . '|' . $path);
            if (!isset($seen[$key])) {
                $paths[] = [
                    'title' => $title,
                    'path'  => $path
                ];
                $seen[$key] = true;
            }
        }
    }

    // Hitung hanya halaman unik
    $users[$userId]['pages'] = ($users[$userId]['pages'] ?? 0) + count($paths);
}
$users[$userId]['journeys'][] = $paths;

}

// Search & limit
$search=$_GET['search'] ?? '';
$limit=in_array(intval($_GET['limit'] ?? 10),[10,20,50])?intval($_GET['limit'] ?? 20):20;

$filteredUsers=[];
if($search!==''){
    foreach($users as $uid=>$info){
        $sourceStr=implode(", ",array_unique($info['sources']));
        if(stripos($uid,$search)!==false || stripos($sourceStr,$search)!==false) $filteredUsers[$uid]=$info;
    }
}else $filteredUsers=$users;

// Pagination
$page=max(1,intval($_GET['page'] ?? 1));
$totalItems=count($filteredUsers);
$totalPages=ceil($totalItems/$limit);
$start=($page-1)*$limit;
$usersPage=array_slice($filteredUsers,$start,$limit,true);

// ================== FUNCTION ==================
function renderJourney($steps, $max=2){
    $html = "<ol>";
    $count = 0;
    $prevTitle = '';
    $realSteps = []; // tampung langkah unik

    foreach($steps as $s){
        if(trim($s['title'])==='') continue;
        if($s['title']==$prevTitle) continue; // hindari duplikat berturut-turut
        $realSteps[] = $s; // simpan step valid
        $prevTitle = $s['title'];
    }

    // render hanya langkah valid
    foreach($realSteps as $i => $s){
        $style = $i < $max ? '' : 'style="display:none"';
        $html .= "<li class='journey-step' $style>
                    <span class='title'>{$s['title']}</span>
                    <span class='path d-none'>{$s['path']}</span>
                  </li>";
    }

    // kalau jumlah real step lebih besar dari max → tampilkan "Show All"
    if(count($realSteps) > $max){
        $html .= "<li class='show-all-toggle'>
                    <a href='#' class='toggle-link badge rounded-pill text-bg-warning fw-bold text-decoration-none' data-shown='0'>
                      Show All
                    </a>
                  </li>";
    }

    $html .= "</ol>";
    return $html;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Ambil semua data (sejak awal install sampai sekarang)
    $allData = fetchMatomo(
        $matomoUrl,
        "Live.getLastVisitsDetails",
        $siteId,
        "range",
        "last3600", // ~10 tahun
        $token,
        [
            "filter_limit" => -1
        ]
    );

    // Header file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="matomo_export.csv"');

    $output = fopen('php://output', 'w');

    // Header tabel (silakan tambah kolom kalau perlu)
    fputcsv($output, [
        'Visit ID',
        'Date',
        'IP',
        'Country',
        'Device',
        'Browser'
    ]);

    // Data baris
    foreach ($allData as $row) {
        fputcsv($output, [
            $row['idVisit'] ?? '',
            $row['serverDate'] ?? '',
            $row['visitIp'] ?? '',
            $row['country'] ?? '',
            $row['deviceType'] ?? '',
            $row['browserName'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>DOKU.Promo User Journey</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
body{padding:0;}
table {
  font-size: 13px;
  table-layout: auto; /* atau tetap fixed jika ingin kolom konsisten */
  width: 100%;
  min-width: 800px; /* memastikan scroll muncul jika container lebih kecil */
}

th, td {
  word-wrap: break-word;
}

th.landing, td.landing,
th.journey, td.journey,
th.exit, td.exit {
  min-width: 25%; /* ganti width:25% */
}

th.user, td.user,
th.source, td.source,
th.sessions, td.sessions,
th.total, td.total {
  width: auto;
}

ol{margin:0;padding-left:18px;}
.badge-view{font-size:0.85rem;margin-left:5px;}
.table-responsive{overflow-y:auto;}
.pagination a {
  margin: 0 2px;
}

.pagination .page-link {
  background-color: #000;
  color: #6c757d;
  border-color: #292c30; /* dark tapi lebih terang dari hitam */
}

.pagination .page-item.active .page-link {
  background-color: red; /* active jadi orange */
  color: #fff;
  border-color: red;
}

.pagination .page-link:hover {
  background-color: #444;
  color: #fff;
}
.table.table-striped tr,
.table.table-striped td {
  border-top: none !important; /* hilangkan border atas (horizontal) */
  border-bottom: none !important; /* hilangkan border bawah */
}

/* Header tabel tetap beda biar jelas */
.table thead th {
  background-color: #fff; !important; /* Tosca gelap */
  color: #000 !important;
}

</style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white pe-3 sticky-top shadow">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"><img src="https://doku.promo/bl-themes/puleka/img/logo.png" width="50">
    <span calss="fw-bold text-light">&nbsp;&nbsp;User behavior</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <form class="d-flex ms-auto" method="get">
        <input type="text" name="search" class="form-control me-2 border-dark" placeholder="Search User ID / Source" value="<?=htmlspecialchars($search)?>">
        <select name="limit" class="form-select me-2 border-dark" style="width:100px;">
          <?php foreach([10,20,50] as $opt): ?>
            <option value="<?= $opt ?>" <?= $opt==$limit?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-dark me-2" type="submit"><i class="bi bi-funnel"></i></button>
        <a href="?logout=1" class="btn btn-outline-dark"><i class="bi bi-box-arrow-right"></i></a>
      </form>
    </div>
  </div>
  <a href="https://doku.promo/anl/rpt/matomo_export_csv.php" class="btn btn-success">
    <i class="bi bi-download"></i>
</a>
</nav>

<div class="container-fluid py-3 bg-white">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <!-- Dashboard Stats Card -->
        <div class="d-flex flex-wrap gap-2 justify-content-center ">
            <div class="card bg-white text-secondary border text-center p-2 rounded-3">
                <div class="small text-secondary">Total Visits All Time</div>
                <div class="fw-bold bg-light rounded-3 mt-1"><h2 class="pt-2 text-black fw-bold"><?= number_format($totalVisitsAll) ?></h2></div>
            </div>
            <div class="card bg-white text-secondary border text-center p-2 rounded-3">
                <div class="small text-secondary">Total Visits Today</div>
                <div class="fw-bold bg-light rounded-3 mt-1"><h2 class="pt-2 text-black fw-bold"><?= number_format($totalVisitsToday) ?></h2></div>
            </div>
            <div class="card bg-white text-secondary border text-center p-2 rounded-3">
                <div class="small text-secondary">Total Visits This Month</div>
                <div class="fw-bold bg-light rounded-3 mt-1"><h2 class="pt-2 text-black fw-bold"><?= number_format($totalVisitsMonth) ?></h2></div>
            </div>
            <div class="card bg-white text-secondary border text-center p-2 rounded-3">
                <div class="small text-secondary">Total Unique Visitors This Month</div>
                <div class="fw-bold bg-light rounded-3 mt-1"><h2 class="pt-2 text-black fw-bold"><?= number_format($totalUniqueMonth) ?></h2></div>
            </div>
            <div class="card bg-white text-secondary border text-center p-2 rounded-3">
                <div class="small text-secondary">Realtime Users (last 30 min)</div>
                <div class="fw-bold bg-light rounded-3 mt-1"><h2 class="pt-2 text-black fw-bold"><?= $visitorsNow ?></h2></div>
            </div>
        </div>
        </div>
    </div>
</div>

<div class="mb-4 p-0" style="overflow-x:auto;">
<table class="table table-hover border-top-0">
<thead class="text-uppercase small">
<tr>
<th class="user border col">User ID</th>
<th class="source border">Sources <i class="bi bi-arrow-right fw-bold float-end"></i></th>
<th class="landing border col-3">Landing Pages <i class="bi bi-arrow-right fw-bold float-end"></i></th>
<th class="journey border col-3">Page Path Journey <i class="bi bi-arrow-right fw-bold float-end"></i></th>
<th class="pages border col-1">Number of Pages</th>
<th class="exit border col-3">Exit Pages</th>
<th class="sessions border">Sessions</th>
<th class="total border">Users</th>
</tr>
</thead>
<tbody class="table-group-divider">
<?php foreach($usersPage as $uid=>$info):
$sources=implode(", ",array_unique($info['sources']));
$sessCnt=count(array_unique($info['sessions']));

$landingsHtml=[];
foreach($info['landings'] as $l) $landingsHtml[]="<span class='title'>{$l['title']}</span><span class='path d-none'>{$l['path']}</span>";
$landingsHtml=implode(", ",$landingsHtml);

$exitsHtml=[];
foreach($info['exits'] as $sessionExit) $exitsHtml[]="<span class='title'>{$sessionExit['title']}</span><span class='path d-none'>{$sessionExit['path']}</span>";
$exitsHtml=implode(", ",$exitsHtml);

$journeysHtml="<ol>";
foreach($info['journeys'] as $steps) $journeysHtml.="<li>".renderJourney($steps)."</li>";
$journeysHtml.="</ol>";
?>
<tr>
<td class="border"><?= $uid ?></td>
<td class="border"><?= $sources ?></td>
<td class="border"><?= $landingsHtml ?></td>
<td class="border"><?= $journeysHtml ?></td>
<td class="border"><?= $info['pages'] ?? 0 ?></td>
<td class="border"><?= $exitsHtml ?></td>
<td class="border"><?= $sessCnt ?></td>
<td>1</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<br><br><br>
<div class="px-3 bg-black pt-2 fixed-bottom border-top border-light">
<nav class="mt-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
        <!-- Pagination -->
<ul class="pagination mb-3 mb-md-0">
    <?php
    $range = 2; // jumlah halaman di kiri/kanan halaman aktif
    $ellipsisShownLeft = false;
    $ellipsisShownRight = false;

    for ($i = 1; $i <= $totalPages; $i++) {
        if (
            $i == 1 || 
            $i == $totalPages || 
            ($i >= $page - $range && $i <= $page + $range)
        ) {
            // Tampilkan halaman
            ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">
                    <?= $i ?>
                </a>
            </li>
            <?php
        } elseif ($i < $page - $range && !$ellipsisShownLeft) {
            // Ellipsis di kiri sekali saja
            ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php
            $ellipsisShownLeft = true;
        } elseif ($i > $page + $range && !$ellipsisShownRight) {
            // Ellipsis di kanan sekali saja
            ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php
            $ellipsisShownRight = true;
        }
    }
    ?>
</ul>


    </div>
</nav>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle Title / Path
function showTitle(){document.querySelectorAll('.title').forEach(el=>el.classList.remove('d-none'));document.querySelectorAll('.path').forEach(el=>el.classList.add('d-none'));}
function showPath(){document.querySelectorAll('.title').forEach(el=>el.classList.add('d-none'));document.querySelectorAll('.path').forEach(el=>el.classList.remove('d-none'));}

// Toggle show all / show less
document.addEventListener('click',function(e){
    if(e.target.matches('.toggle-link')){
        e.preventDefault();
        let liParent=e.target.closest('ol');
        let steps=liParent.querySelectorAll('.journey-step');
        let shown=e.target.getAttribute('data-shown');
        if(shown=='0'){
            steps.forEach(s=>s.style.display='list-item');
            e.target.textContent='Show Less';
            e.target.setAttribute('data-shown','1');
        } else {
            steps.forEach((s,i)=>{ if(i>=3) s.style.display='none'; });
            e.target.textContent='Show All';
            e.target.setAttribute('data-shown','0');
        }
    }
});

// Tambahkan tombol toggle Title / Path
const toggleHtml=`<div class="btn-group ms-2" role="group">
<button type="button" class="btn btn-outline-dark active" id="btnTitle"><i class="bi bi-fonts"></i></button>
<button type="button" class="btn btn-outline-dark" id="btnPath"><i class="bi bi-link-45deg"></i></button>
</div>`;
document.querySelector('nav.navbar').insertAdjacentHTML('beforeend',toggleHtml);

document.getElementById('btnTitle').addEventListener('click',()=>{
    showTitle();
    document.getElementById('btnTitle').classList.add('active');
    document.getElementById('btnPath').classList.remove('active');
});
document.getElementById('btnPath').addEventListener('click',()=>{
    showPath();
    document.getElementById('btnPath').classList.add('active');
    document.getElementById('btnTitle').classList.remove('active');
});

// Realtime update
setInterval(()=>{location.reload();},60000);
</script>
</body>
</html>
