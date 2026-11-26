<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// hide all error
error_reporting(0);
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  // Decide progressive mode before touching the router. The default "all
  // users" view must send its shell immediately; profile names/counts arrive
  // through the streaming endpoint.
  $prof = isset($prof) ? (string)$prof : '';
  $comm = isset($comm) ? (string)$comm : '';
  $exp = isset($_GET['exp']) ? (string)$_GET['exp'] : '';
  $progressiveUsers = ($prof === 'all' && $comm === '' && $exp === '' && !isset($_GET['page']) && !isset($_GET['per_page']));

  if ($progressiveUsers) {
    $getprofile = array();
    $TotalReg2 = 0;
  } else {
    // Read profiles before the (possibly very large) user list.
    $profileRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/profile/print', array(), 60);
    $getprofile = (array)$profileRead['data'];
    $TotalReg2 = count($getprofile);
  }

  // Fetch only the rows needed for the active filter. The profile-only
  // count source is cached separately, so filtering never downloads the full
  // detailed list just to populate the profile dropdown.
  $usersCached = false;
  $usersCacheTime = 0;
  if (!$progressiveUsers) {
  $userFields = '.id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment,disabled,limit-uptime,limit-bytes-total';
  $userParams = array('.proplist' => $userFields);
  if ($prof !== '' && $prof !== 'all') { $userParams['?profile'] = $prof; }
  if ($comm !== '') { $userParams['?comment'] = $comm; }
  if ($exp !== '') { $userParams['?limit-uptime'] = '1s'; }
  $usersRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/print', $userParams, 15);
  $allusersRaw = (array)$usersRead['data'];
  $usersCached = !empty($usersRead['cached']);
  $usersCacheTime = !empty($usersRead['created_at']) ? (int)$usersRead['created_at'] : 0;
  $getuser = $allusersRaw;
  if ($prof !== '' && $prof !== 'all') {
    $getuser = array_values(array_filter($getuser, function ($userRow) use ($prof) {
      return isset($userRow['profile']) && (string)$userRow['profile'] === $prof;
    }));
  }
  if ($comm !== '') {
    $getuser = array_values(array_filter($getuser, function ($userRow) use ($comm) {
      return isset($userRow['comment']) && (string)$userRow['comment'] === $comm;
    }));
  }
  if ($exp !== '') {
    $getuser = array_values(array_filter($getuser, function ($userRow) {
      return isset($userRow['limit-uptime']) && (string)$userRow['limit-uptime'] === '1s';
    }));
  }
  $TotalReg = count($getuser);
  $counttuser = $TotalReg;

  $profileCountSource = $allusersRaw;
  if ($prof !== '' || $comm !== '' || $exp !== '') {
    $profileCountRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/print', array('.proplist' => 'profile'), 60);
    $profileCountSource = (array)$profileCountRead['data'];
  }
  } else {
    $allusersRaw = array();
    $getuser = array();
    $usersCached = false;
    $usersCacheTime = 0;
    $TotalReg = 0;
    $counttuser = 0;
  }

  $profileCounts = array();
  foreach ($getprofile as $profileRow) {
    $pn = isset($profileRow['name']) ? (string)$profileRow['name'] : '';
    if ($pn !== '') { $profileCounts[$pn] = 0; }
  }
  if (!$progressiveUsers) {
    $profileCountSource = $allusersRaw;
    if ($prof !== '' || $comm !== '' || $exp !== '') {
      $profileCountRead = mikhmon_router_cached($API, $session, '/ip/hotspot/user/print', array('.proplist' => 'profile'), 60);
      $profileCountSource = (array)$profileCountRead['data'];
    }
    $totalAllUsers = count($profileCountSource);
    foreach ($profileCountSource as $userRow) {
      $pn = isset($userRow['profile']) ? (string)$userRow['profile'] : '';
      if ($pn !== '') { $profileCounts[$pn] = isset($profileCounts[$pn]) ? $profileCounts[$pn] + 1 : 1; }
    }
  } else {
    $totalAllUsers = 0;
  }

  $allusers = $getuser;
  $totalUsers = count($allusers);
  $page = \Mikhmon\Support\Pagination::page(isset($_GET['page']) ? $_GET['page'] : 1);
  $perPage = \Mikhmon\Support\Pagination::perPage(isset($_GET['per_page']) ? $_GET['per_page'] : 50);
  $pages = \Mikhmon\Support\Pagination::pages($totalUsers, $perPage);
  if ($page > $pages) { $page = $pages; }
  $getuser = array_slice($allusers, ($page - 1) * $perPage, $perPage);
  $TotalReg = count($getuser);
  $baseUrl = $_SERVER['REQUEST_URI'];
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> <?= $_users ?>
      <span style="font-size: 14px">
        <?php
        if ($counttuser == 0 && empty($progressiveUsers)) {
          echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "</script>";
        } ?>
         &nbsp; | &nbsp; <a href="./?hotspot-user=add&session=<?= $session; ?>" title="Add User"><i class="fa fa-user-plus"></i> <?= $_add ?></a>
        &nbsp; | &nbsp; <a href="./?hotspot-user=generate&session=<?= $session; ?>" title="Generate User"><i class="fa fa-users"></i> <?= $_generate ?></a>
         &nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=script" title="Download User List as Mikrotik Script"><i class="fa fa-download"></i> Script</a>&nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=csv" title="Download User List as CSV"><i class="fa fa-download"></i> CSV</a>
        </span>  &nbsp;
        <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
    </h3>
    
</div>
<div class="card-body">
  <div class="row">
   <div class="col-6 pd-t-5 pd-b-5">
  <div class="input-group">
    <div class="input-group-4 col-box-4">
      <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?>">
    </div>
    <div class="input-group-4 col-box-4">
      <select style="padding:5px;" class="group-item group-item-m" onchange="if (this.value) { window.location.assign(this.value); }" title="Filter by Profile">
        <option value="" <?= empty($prof) ? 'selected' : ''; ?> disabled><?= $_profile ?> </option>
        <option value="./?hotspot=users&amp;profile=all&amp;session=<?= urlencode((string)$session); ?>" <?= $prof === 'all' ? 'selected' : ''; ?>><?= $_show_all; ?></option>
      <?php
      for ($i = 0; $i < $TotalReg2; $i++) {
        $profile = $getprofile[$i];
        $profileName = isset($profile['name']) ? (string)$profile['name'] : '';
        $profileUrl = './?hotspot=users&profile=' . rawurlencode($profileName) . '&session=' . rawurlencode((string)$session);
        $profileCount = isset($profileCounts[$profileName]) ? (int)$profileCounts[$profileName] : 0;
        echo '<option value="' . mikhmon_e($profileUrl) . '"' . ($prof === $profileName ? ' selected' : '') . '>' . mikhmon_e($profileName) . '(' . number_format($profileCount, 0, ',', '.') . ')</option>';
      }
      ?>
    </select>
  </div>
  <div class="input-group-4 col-box-4">
    <select style="padding:5px;" class="group-item group-item-r" id="comment" name="comment" onchange="location = './?hotspot=users&comment='+ this.value +'&session=<?= $session;?>';">
    <?php
    if ($comm != "") {
    } else {
      echo "<option value=''>".$_comment."</option>";
    }
    $TotalRegAll = count($allusers);
    for ($i = 0; $i < $TotalRegAll; $i++) {
      $ucomment = $allusers[$i]['comment'];
      $uprofile = $allusers[$i]['profile'];
      $acomment .= ",".$ucomment."#". $uprofile;
    }

    $ocomment=  explode(",",$acomment);
    
    $comments=array_count_values($ocomment) ;
    foreach ($comments as $tcomment=>$value) {

      if (is_numeric(substr($tcomment, 3, 3))) {
       
        echo "<option value='" . explode("#",$tcomment)[0] . "' >". explode("#",$tcomment)[0]." ".explode("#",$tcomment)[1]. " [".$value. "]</option>";
       }
 
    }

    ?>
    </select>
  </div>
  </div>
  </div>
 
  <div class="col-6">
    <?php if ($comm != "") { ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete username by comment (<?= $comm; ?>)?')){loadpage('./?remove-hotspot-user-by-comment=<?= $comm; ?>&session=<?= $session; ?>');loader();}else{}" title="Remove user by comment <?= $comm; ?>">  <i class="fa fa-trash"></i> <?= $_by_comment ?></button>
    <?php ; }else if ($exp == "1"){ ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete users?')){loadpage('./?remove-hotspot-user-expired=1&session=<?= $session; ?>');loader();}else{}" title="Remove user expired">  <i class="fa fa-trash"></i> Expired Users</button>
      <?php } ?>
  <script>
    function printV(a,b){
    var comm = document.getElementById('comment').value;
    var url = "./voucher/print.php?id="+comm+"&"+a+"="+b+"&session=<?= $session; ?>";
    if (comm === "" ){
      <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
      alert('Silakan pilih salah satu Comment terlebih dulu!');
      <?php
    } else { ?>
      alert('Please choose one of the Comments first!');
      <?php
    } ?>
    }else{
      var win = window.open(url, '_blank');
      win.focus();
    }}
  </script>
  <button class="btn bg-primary" title='Print' onclick="printV('qr','no');"><i class="fa fa-print"></i> <?= $_print_default ?></button>
  <button class="btn bg-primary" title='Print QR' onclick="printV('qr','yes');"><i class="fa fa-print"></i> <?= $_print_qr ?></button>
  <button class="btn bg-primary" title='Print Small'onclick="printV('small','yes');"><i class="fa fa-print"></i> <?= $_print_small ?></button>
  </div>
</div>
<div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="min-width:50px;" class="align-middle text-center" id="cuser"><?= $counttuser; ?></th>
    <th style="min-width:50px;" class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th>Print</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
	  <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Mac Address</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_uptime_user ?></th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes In</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes Out</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
    </tr>
  </thead>
  <tbody id="mmUsersBody">
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $userdetails = $getuser[$i];
  $uid = $userdetails['.id'];
  $userver = $userdetails['server'];
  $uname = $userdetails['name'];
  $upass = $userdetails['password'];
  $uprofile = $userdetails['profile'];
  $umacadd = $userdetails['mac-address'];
  $uuptime = formatDTM($userdetails['uptime']);
  $ubytesi = formatBytes($userdetails['bytes-in'], 2);
  $ubyteso = formatBytes($userdetails['bytes-out'], 2);

  $ucomment = $userdetails['comment'];
  $udisabled = $userdetails['disabled'];
  $utimelimit = $userdetails['limit-uptime'];
  if ($utimelimit == '1s') {
    $utimelimit = ' expired';
  } else {
    $utimelimit = ' ' . $utimelimit;
  }
  $udatalimit = $userdetails['limit-bytes-total'];
  if ($udatalimit == '') {
    $udatalimit = '';
  } else {
    $udatalimit = ' ' . formatBytes($udatalimit, 2);
  }

  echo "<tr>";
  ?>
  <td style='text-align:center;'>  <i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete username (<?= $uname; ?>)?')){loadpage('./?remove-hotspot-user=<?= $uid; ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $uname; ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  <?php
  if ($udisabled == "true") {
    $uriprocess = "'./?enable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="text-warning pointer" title="Enable User ' . $uname . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-lock "></i></span></td>';
  } else {
    $uriprocess = "'./?disable-hotspot-user=" . $uid . "&session=" . $session."'";
    echo '<span class="pointer" title="Disable User ' . $uname . '"  onclick="loadpage('.$uriprocess.')"><i class="fa fa-unlock "></i></span></td>';
  }
  echo "<td>" . $userver . "</td>";
  if ($uname == $upass) {
    $usermode = "vc";
  } else {
    $usermode = "up";
  }
  $popup = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . $uname . "&qr=no&session=" . $session . "','_blank','width=320,height=550').print();";
  $popupQR = "javascript:window.open('./voucher/print.php?user=" . $usermode . "-" . $uname . "&qr=yes&session=" . $session . "','_blank','width=320,height=550').print();";
  echo "<td><a title='Open User " . $uname . "' href=./?hotspot-user=" . $uid . "&session=" . $session . "><i class='fa fa-edit'></i> " . $uname . " </a>";
  echo '</td><td class"text-center"><a title="Print ' . $uname . '" href="' . $popup . '"><i class="fa fa-print"></i></a> &nbsp <a title="Print ' . $uname . '" href="' . $popupQR . '"><i class="fa fa-qrcode"></i> </a></td>';
  echo "<td>" . $uprofile . "</td>";
  echo "<td style=' text-align:left'>" . $umacadd . "</td>";
  echo "<td style=' text-align:right'>" . $uuptime . "</td>";
  echo "<td style=' text-align:right'>" . $ubytesi . "</td>";
  echo "<td style=' text-align:right'>" . $ubyteso . "</td>";
  echo "<td>";
  if ($uname == "default-trial") {
  } else if (substr($ucomment,0,3) == "vc-" || substr($ucomment,0,3) == "up-") {
    echo "<a href=./?hotspot=users&comment=" . $ucomment . "&session=" . $session . " title='Filter by " . $ucomment . "'><i class='fa fa-search'></i> ". $ucomment." ". $udatalimit ." ".$utimelimit . "</a>";
  } else if ($utimelimit == ' expired') {
    echo "<a href=./?hotspot=users&profile=all&exp=1&session=" . $session . " title='Filter by expired'><i class='fa fa-search'></i> " . $ucomment." ". $udatalimit ." ".$utimelimit . "</a>";
  }else{
    echo $ucomment.' ';
  }
  echo  "</td>";


}
?>
  </tr>
  </tbody>
</table>
</div>
<div id="mmUsersPager"><?= mikhmon_pager($totalUsers, $page, $perPage, $baseUrl); ?></div>
<script src="./js/mm-pagination.js"></script>
<script>
(function () {
  var progressive = <?= !empty($progressiveUsers) ? 'true' : 'false'; ?>;
  if (!progressive) { return; }
  var body = document.getElementById('mmUsersBody');
  var pager = document.getElementById('mmUsersPager');
  var countEl = document.getElementById('cuser');
  var loader = document.getElementById('loader');
  if (!body) { return; }
  var rows = [];
  var page = 1;
  var perPage = 50;
  var pending = 0;
  var comments = {};
  body.innerHTML = '<tr id="mmUsersLoading"><td colspan="10" class="mm-muted"><i class="fa fa-circle-o-notch fa-spin"></i> Memuat data pengguna dari MikroTik...</td></tr>';
  function textCell(value) { var td = document.createElement('td'); td.textContent = value == null ? '' : String(value); return td; }
  function addLink(td, href, icon, title, onclick) {
    var a = document.createElement('a'); a.href = href || '#'; a.title = title || ''; if (onclick) { a.setAttribute('onclick', onclick); }
    var i = document.createElement('i'); i.className = icon; a.appendChild(i); td.appendChild(a); return a;
  }
  var config = <?= json_encode(array('session' => (string)$session, 'currency' => (string)$currency), JSON_UNESCAPED_SLASHES); ?>;
  function renderPage() {
    body.innerHTML = '';
    var start = (page - 1) * perPage;
    var end = Math.min(rows.length, start + perPage);
    for (var i = start; i < end; i++) {
      var r = rows[i], tr = document.createElement('tr'), td;
      td = document.createElement('td'); td.style.textAlign = 'center';
      addLink(td, './?remove-hotspot-user=' + encodeURIComponent(r.id) + '&session=' + encodeURIComponent(config.session), 'fa fa-minus-square text-danger', 'Remove ' + r.name, "return confirm('Hapus user " + String(r.name).replace(/'/g, "\\'") + "?')");
      td.appendChild(document.createTextNode(' '));
      var disabled = String(r.disabled) === 'true';
      addLink(td, './?' + (disabled ? 'enable' : 'disable') + '-hotspot-user=' + encodeURIComponent(r.id) + '&session=' + encodeURIComponent(config.session), disabled ? 'fa fa-lock text-warning' : 'fa fa-unlock', (disabled ? 'Enable ' : 'Disable ') + r.name);
      tr.appendChild(td);
      tr.appendChild(textCell(r.server));
      td = document.createElement('td'); var edit = document.createElement('a'); edit.href = './?hotspot-user=' + encodeURIComponent(r.id) + '&session=' + encodeURIComponent(config.session); edit.title = 'Open User ' + r.name; edit.innerHTML = '<i class="fa fa-edit"></i> '; edit.appendChild(document.createTextNode(r.name)); td.appendChild(edit); tr.appendChild(td);
      td = document.createElement('td'); var mode = r.user_mode || 'up'; addLink(td, './voucher/print.php?user=' + encodeURIComponent(mode + '-' + r.name) + '&qr=no&session=' + encodeURIComponent(config.session), 'fa fa-print', 'Print'); td.appendChild(document.createTextNode(' ')); addLink(td, './voucher/print.php?user=' + encodeURIComponent(mode + '-' + r.name) + '&qr=yes&session=' + encodeURIComponent(config.session), 'fa fa-qrcode', 'Print QR'); tr.appendChild(td);
      tr.appendChild(textCell(r.profile)); tr.appendChild(textCell(r.mac)); tr.appendChild(textCell(r.uptime)); tr.appendChild(textCell(r.bytes_in)); tr.appendChild(textCell(r.bytes_out));
      td = document.createElement('td'); var comment = String(r.comment || '');
      if (comment.indexOf('vc-') === 0 || comment.indexOf('up-') === 0) {
        var ca = document.createElement('a'); ca.href = './?hotspot=users&comment=' + encodeURIComponent(comment) + '&session=' + encodeURIComponent(config.session); ca.title = 'Filter by ' + comment; ca.innerHTML = '<i class="fa fa-search"></i> '; ca.appendChild(document.createTextNode(comment + (r.limit_data || '') + (r.limit_uptime || ''))); td.appendChild(ca);
      } else { td.textContent = comment + (r.limit_data || '') + (r.limit_uptime || ''); }
      tr.appendChild(td); body.appendChild(tr);
    }
    if (countEl) { countEl.textContent = rows.length.toLocaleString('id-ID'); }
    if (loader) { loader.style.display = 'none'; }
    if (pager) {
      if (!rows.length) { pager.innerHTML = ''; }
      else {
        MMPagination.mount(pager, {
          total: rows.length,
          page: page,
          perPage: perPage,
          onPage: function (next) { page = next; renderPage(); },
          onPerPage: function (size) { perPage = size; page = 1; renderPage(); }
        });
      }
    }
  }
  function schedule() { if (pending) { return; } pending = setTimeout(function () { pending = 0; renderPage(); }, 150); }
  fetch('./process/live_users.php?' + location.search.replace(/^\?/, ''), {credentials: 'same-origin', headers: {'Accept': 'application/x-ndjson'}})
    .then(function (res) { if (!res.ok || !res.body) { throw new Error('HTTP ' + res.status); } var reader = res.body.getReader(), decoder = new TextDecoder(), buf = '';
      function pump() { return reader.read().then(function (part) { if (part.done) { return; } buf += decoder.decode(part.value, {stream: true}); var lines = buf.split('\n'); buf = lines.pop(); lines.forEach(function (line) { if (!line.trim()) { return; } var e; try { e = JSON.parse(line); } catch (x) { return; } if (e.type === 'row') { rows.push(e.data); var c = String(e.data.comment || ''); if (c.indexOf('vc-') === 0 || c.indexOf('up-') === 0) { var ck = c + '\u0000' + String(e.data.profile || ''); comments[ck] = (comments[ck] || 0) + 1; } schedule(); } if (e.type === 'profiles') { var sel = document.querySelector('select[title="Filter by Profile"]'); Object.keys(e.data || {}).forEach(function (name) { var opt = document.querySelector('option[data-profile-name="' + String(name).replace(/"/g, '\\"') + '"]'); if (!opt && sel) { opt = document.createElement('option'); opt.value = './?hotspot=users&profile=' + encodeURIComponent(name) + '&session=' + encodeURIComponent(config.session); opt.setAttribute('data-profile-name', name); sel.appendChild(opt); } if (opt) { opt.textContent = name + '(' + Number(e.data[name]).toLocaleString('id-ID') + ')'; } }); } if (e.type === 'done') { if (loader) { loader.style.display = 'none'; } if (e.rows === 0) { body.innerHTML = '<tr><td colspan="10" class="mm-muted">Tidak ada user.</td></tr>'; } var cs = document.getElementById('comment'); if (cs) { while (cs.options.length > 1) { cs.remove(1); } Object.keys(comments).forEach(function (k) { var parts = k.split('\u0000'); var o = document.createElement('option'); o.value = parts[0]; o.textContent = parts[0] + ' ' + parts[1] + ' [' + comments[k] + ']'; cs.appendChild(o); }); } renderPage(); } if (e.type === 'error') { body.innerHTML = '<tr><td colspan="10" class="text-red">Gagal memuat user: ' + String(e.error) + '</td></tr>'; } }); return pump(); }); } return pump(); })
    .catch(function (err) { body.innerHTML = '<tr><td colspan="10" class="text-red">Gagal memuat user: ' + String(err.message) + '</td></tr>'; });
})();
</script>
</div>
</div>
</div>
</div>

	
	
