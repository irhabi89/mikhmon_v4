# Dokumentasi Command & Script MikroTik (RouterOS)

**MIKHMON v4 — GHAIB.NET Edition**  
Maintained by **GHAIB.NET** · Developer: **quiqxiq**

Dokumen ini merangkum seluruh **RouterOS API command** dan **script RouterOS** yang digunakan serta dihasilkan oleh project ini di ketiga branch (`feat/report-sqlite-ui`, `parto`, dan `99barokah`).

---

## 1. Daftar Command RouterOS API

Seluruh komunikasi ke MikroTik dilakukan melalui RouterOS API (`lib/routeros_api.class.php` dan `src/Router/NativeRouterClient.php`).

### A. Autentikasi, Sistem, Logging & Interface
| Command | Tujuan | Sumber |
|---|---|---|
| `/login` | Autentikasi sesi RouterOS API (legacy & modern challenge/plaintext) | `lib/routeros_api.class.php`, `src/Router/NativeRouterClient.php` |
| `/system/identity/print` | Mengambil nama identitas router | `settings/sessions.php` |
| `/system/clock/print` | Mengambil tanggal & jam router untuk dashboard | `dashboard/home.php`, `dashboard/aload.php` |
| `/system/resource/print` | Mengambil CPU, RAM, uptime, board, dan versi RouterOS (termasuk deteksi ROS 6 vs ROS 7 di `mikhmon_is_ros6()`) | `dashboard/home.php`, `dashboard/aload.php`, `include/mikhmon_scripts.php` |
| `/system/routerboard/print` | Mengambil model & serial RouterBoard | `dashboard/home.php`, `dashboard/aload.php` |
| `/system/logging/print` | Mengecek rule logging hotspot dengan prefix `->` | `dashboard/home.php`, `dashboard/aload.php` |
| `/system/logging/add` | Menambahkan rule logging `topics=hotspot,info,debug action=disk prefix="->"` jika belum ada | `dashboard/home.php`, `dashboard/aload.php` |
| `/log/print` | Membaca log hotspot (`?topics=hotspot,info,debug`) dan log user | `dashboard/home.php`, `dashboard/aload.php`, `hotspot/log.php` |
| `/interface/print` | Mengambil daftar interface untuk grafik traffic | `dashboard/home.php` |
| `/interface/monitor-traffic` | Memantau trafik Rx/Tx realtime pada interface terpilih | `traffic/traffic.php` |
| `/system/reboot` | Reboot router dari menu sistem | `process/reboot.php` |
| `/system/shutdown` | Shutdown router dari menu sistem | `process/shutdown.php` |

### B. Hotspot Server, User, Active, Host, Cookie & IP Binding
| Command | Tujuan | Sumber |
|---|---|---|
| `/ip/hotspot/print` | Mengambil daftar server Hotspot | `hotspot/adduser.php`, `hotspot/generateuser.php`, `hotspot/listquickprint.php`, `dashboard/aload.php` |
| `/ip/hotspot/user/print` | Mengambil daftar user hotspot (`count-only`, `.proplist`, filter profile/name) | `hotspot/users.php`, `process/live_users.php`, `dashboard/home.php`, `dashboard/aload.php` |
| `/ip/hotspot/user/add` | Menambah user / voucher hotspot (satuan & batch generate) | `hotspot/adduser.php`, `hotspot/generateuser.php` |
| `/ip/hotspot/user/set` | Mengubah properti user atau enable/disable user | `hotspot/userbyname.php`, `process/enablehotspotuser.php`, `process/disablehotspotuser.php` |
| `/ip/hotspot/user/remove` | Menghapus user hotspot | `process/removehotspotuser.php` |
| `/ip/hotspot/user/reset-counters` | Mereset counter trafik user hotspot | `process/resetuser.php` |
| `/ip/hotspot/active/print` | Mengambil daftar user hotspot yang sedang aktif | `hotspot/hotspotactive.php`, `dashboard/home.php`, `dashboard/aload.php` |
| `/ip/hotspot/active/remove` | Memutuskan sesi aktif user hotspot | `process/removeuseractive.php` |
| `/ip/hotspot/host/print` | Mengambil daftar host hotspot (all/bypassed/authorized) | `hotspot/hosts.php`, `process/live_hotspot.php` |
| `/ip/hotspot/host/remove` | Menghapus entri host hotspot | `process/removehost.php` |
| `/ip/hotspot/cookie/print` | Mengambil daftar cookie MAC hotspot | `hotspot/cookies.php`, `process/live_hotspot.php` |
| `/ip/hotspot/cookie/remove` | Menghapus cookie hotspot | `process/removecookie.php` |
| `/ip/hotspot/ip-binding/print` | Mengambil daftar IP binding | `hotspot/ipbinding.php`, `process/live_hotspot.php` |
| `/ip/hotspot/ip-binding/set` | Mengaktifkan/menonaktifkan IP binding | `process/enableipbinding.php`, `process/disableipbinding.php` |
| `/ip/hotspot/ip-binding/remove` | Menghapus IP binding | `process/removeipbinding.php` |

### C. User Profile, Queue, IP Pool, DHCP & ARP
| Command | Tujuan | Sumber |
|---|---|---|
| `/ip/hotspot/user/profile/print` | Membaca profil hotspot (termasuk script `on-login`) | `hotspot/userprofile.php`, `hotspot/userprofilebyname.php`, `src/Router/OnloginUpgrader.php` |
| `/ip/hotspot/user/profile/add` | Membuat profil hotspot baru beserta `on-login` | `hotspot/adduserprofile.php` |
| `/ip/hotspot/user/profile/set` | Memperbarui profil hotspot / meng-upgrade `on-login` | `hotspot/userprofilebyname.php`, `src/Router/OnloginUpgrader.php` |
| `/ip/hotspot/user/profile/remove` | Menghapus profil hotspot | `process/removeuserprofile.php` |
| `/queue/simple/print` | Mengambil daftar parent queue untuk form profil | `hotspot/adduserprofile.php`, `hotspot/userprofilebyname.php` |
| `/queue/simple/remove` | Menghapus simple queue terkait | `process/removeuprofile.php` |
| `/ip/pool/print` | Mengambil daftar IP pool | `hotspot/adduserprofile.php`, `hotspot/userprofilebyname.php`, `ppp/*` |
| `/ip/dhcp-server/lease/print` | Mengambil daftar DHCP lease | `dhcp/dhcpleases.php` |
| `/ip/dhcp-server/lease/remove` | Menghapus DHCP lease | `process/removedhcplease.php` |
| `/ip/arp/print` | Mengambil tabel ARP | `dhcp/arp.php` |
| `/ip/arp/remove` | Menghapus entri ARP | `process/removearp.php` |

### D. System Script & Scheduler (Laporan Penjualan, Quick Print, Expiry)
| Command | Tujuan | Sumber |
|---|---|---|
| `/system/script/print` | Membaca record penjualan (`comment=mikhmon`), template quick print, atau `count-only` | `src/Sync/Scanner.php`, `src/Sync/SalesSyncService.php`, `process/live_sales.php`, `hotspot/listquickprint.php` |
| `/system/script/add` | Menambah entri Quick Print (atau dipanggil dari `on-login` untuk mencatat penjualan) | `hotspot/listquickprint.php`, `include/mikhmon_scripts.php` |
| `/system/script/set` | Memperbarui entri Quick Print | `hotspot/listquickprint.php` |
| `/system/script/remove` | Menghapus record penjualan setelah import/hapus data, atau menghapus Quick Print | `src/Sync/ImportedSalesCleaner.php`, `src/Sync/SalesSyncService.php`, `report/selling.php`, `hotspot/listquickprint.php` |
| `/system/scheduler/print` | Membaca scheduler monitor profil (`Monitor Profile ...`) | `hotspot/userprofilebyname.php`, `hotspot/userprofile.php` |
| `/system/scheduler/add` | Menambah scheduler background expiry profil | `hotspot/adduserprofile.php`, `hotspot/userprofilebyname.php` |
| `/system/scheduler/set` | Memperbarui scheduler background expiry profil | `hotspot/adduserprofile.php`, `hotspot/userprofilebyname.php` |
| `/system/scheduler/remove` | Menghapus scheduler saat mode expiry dimatikan / profil dihapus | `hotspot/adduserprofile.php`, `hotspot/userprofilebyname.php`, `process/removeuserprofile.php` |

### E. PPP / PPPoE (Khusus Branch `99barokah`)
| Command | Tujuan | Sumber |
|---|---|---|
| `/ppp/secret/print`, `/ppp/secret/add`, `/ppp/secret/set` | Manajemen PPPoE secret | `ppp/pppsecrets.php`, `ppp/addsecret.php`, `ppp/secretbyname.php`, `process/psecret.php` |
| `/ppp/active/print`, `/ppp/active/remove` | Memantau & memutus sesi PPP aktif | `ppp/pppactive.php`, `process/removepactive.php` |
| `/ppp/profile/print`, `/ppp/profile/add`, `/ppp/profile/set` | Manajemen PPP profile & auto-isolir `on-up` | `ppp/pppprofile.php`, `ppp/addpppprofile.php`, `ppp/profilebyname.php` |
| `/interface/bridge/print`, `/bridge/bridge/print` | Mengambil daftar bridge untuk form PPP profile | `ppp/addpppprofile.php`, `ppp/profilebyname.php` |

---

## 2. Format Record Penjualan di `/system/script`

Setiap transaksi penjualan yang dicatat oleh script `on-login` disimpan pada `/system/script` dengan `comment="mikhmon"` dan `name` berformat:

```text
<date>-|-<time>-|-<username>-|-<price>-|-<ip_address>-|-<mac_address>-|-<validity>-|-<profile>-|-<comment>|#MMSRV1#|<server>
```

Contoh:
```text
nov/28/2026-|-14:10:05-|-vc1234-|-5000-|-10.5.50.12-|-AA:BB:CC:DD:EE:FF-|-1d-|-5rb-|-vc-101|#MMSRV1#|hotspot1
```

> Catatan: Suffix `|#MMSRV1#|<server>` bersifat opsional untuk kompatibilitas mundur dengan record lama Mikhmon v3.

---

## 3. Script RouterOS Lengkap yang Dihasilkan Aplikasi

### A. Script `on-login` — RouterOS v7 (Full Inline)

Dihasilkan oleh `mikhmon_v3_onlogin_script()` (`include/mikhmon_scripts.php`) dan `hotspot/adduserprofile.php` / `hotspot/userprofilebyname.php` ketika router terdeteksi sebagai RouterOS v7+.

1. **Header metadata profil** (`:put`) untuk dibaca Mikhmon saat menampilkan detail profil:
   ```routeros
   :put (",{{expmode}},{{price}},{{validity}},{{sprice}},,{{getlock}},")
   ```
   Jika `expmode = 0` (tanpa masa aktif), seluruh `on-login` hanya berisi:
   ```routeros
   :put (",,{{price}},,,noexp,{{getlock}},")
   ```
   *(ditambah snippet Lock MAC jika `Lock User = Enable`).*

2. **Body `on-login` lengkap (mode `rem`, `ntf`, `remc`, `ntfc`):**
   ```routeros
   :local date [ /system clock get date ];
   :local time [ /system clock get time ];
   :local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
   :local year;
   :local monthname;
   :local day;
   :if ([:pick $date 4 5] = "-") do={
     :set year [:pick $date 0 4];
     :set day [:pick $date 8 10];
     :local mnum1 [:tonum [:pick $date 5 7]];
     :set monthname [:pick $mnames (($mnum1 - 1) * 3) (($mnum1 - 1) * 3 + 3)];
   } else={
     :set year [:pick $date 7 11];
     :set day [:pick $date 4 6];
     :set monthname [:pick $date 0 3];
   }
   :local legacy "$monthname/$day/$year";
   :local comment [ /ip hotspot user get [ /ip hotspot user find where name="$user" ] comment ];
   :local ucode [:pick $comment 0 2];
   :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={
     /sys sch add name="$user" disable=no start-date=$date interval="{{validity}}";
     :delay 2s;
     :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run ];
     :local expLegacy;
     :local getxp [len $exp];
     :if ($getxp = 8) do={
       :set expLegacy "$legacy $exp";
     } else={
       :if ($getxp = 15) do={
         :local d [:pick $exp 0 6];
         :local t [:pick $exp 7 16];
         :set expLegacy "$d/$year $t";
       } else={
         :if ([:pick $exp 4 5] = "-") do={
           :local ey [:pick $exp 0 4];
           :local ed [:pick $exp 8 10];
           :local mnum2 [:tonum [:pick $exp 5 7]];
           :local emn [:pick $mnames (($mnum2 - 1) * 3) (($mnum2 - 1) * 3 + 3)];
           :local et [:pick $exp 11 [:len $exp]];
           :set expLegacy "$emn/$ed/$ey $et";
         } else={
           :set expLegacy $exp;
         }
       }
     }
     /ip hotspot user set comment="$expLegacy" [find where name="$user"];
     /sys sch remove [find where name="$user"];
     {{RECORD_AND_WEBHOOK_PART}}
     {{LOCK_MAC_PART}}
   }
   ```

3. **Bagian pencatatan penjualan + server lookup (`remc` / `ntfc`) di ROS 7:**
   ```routeros
   :local mac $"mac-address";
   :local time [/system clock get time ];
   :local mmsrv "";
   :foreach mma in=[/ip/hotspot/active/find where user=$user] do={ :set mmsrv [ /ip/hotspot/active/get $mma server ] };
   :if ($mmsrv = "") do={ :local mmu [/ip/hotspot/user/find where name=$user]; :if ([:len $mmu] > 0) do={ :set mmsrv [/ip/hotspot/user/get $mmu server] } };
   :if ($mmsrv = "") do={ :local mmh [/ip/hotspot/find where interface=$interface]; :if ([:len $mmh] > 0) do={ :set mmsrv [/ip/hotspot/get $mmh name] } };
   :local sname "$legacy-|-$time-|-$user-|-{{price}}-|-$address-|-$mac-|-{{validity}}-|-{{profile}}-|-$comment|#MMSRV1#|$mmsrv";
   /system script add name=$sname owner="$monthname$year" source=$legacy comment=mikhmon;
   {{WEBHOOK_PART}}
   ```

---

### B. Script `on-login` — RouterOS v6 (Compact Mode)

RouterOS v6 menolak field `on-login` yang terlalu panjang jika digabung dengan webhook. Fungsi `mikhmon_v3_compact_onlogin()` (`include/mikhmon_scripts.php`) menghasilkan script ringkas yang tetap mencatat server (`|#MMSRV1#|`), mengirim webhook, dan membuat scheduler expiry per user:

```routeros
:put (",{{expmode}},{{price}},{{validity}},{{sprice}},,{{getlock}},");
{
  :local c [/ip hotspot user get [find where name=$user] comment];
  :local uc [:pic $c 0 2];
  :if ($uc = "vc" or $uc = "up" or $c = "") do={
    :local dt [/system clock get date];
    :local tm2 [/system clock get time];
    :local sv "$server";
    :if ([:len $sv] = 0) do={
      :local h [/ip hotspot find where interface=$interface];
      :if ([:len $h] > 0) do={:set sv [/ip hotspot get $h name]}
    };
    :local tm [/system clock get time];
    :local sname "$dt-|-$tm-|-$user-|-{{price}}-|-$address-|-$mac-|-{{validity}}-|-{{profile}}-|-$comment|#MMSRV1#|$sv";
    /system script add name=$sname source="compact" comment=mikhmon;
    {{WEBHOOK_PART}}
    /ip hotspot user set comment="$dt $tm2" [find where name=$user];
    :if ([:len [/system scheduler find where name=$user]] > 0) do={/system scheduler remove [find where name=$user]};
    :local ev ("/ip hotspot user remove [find name=" . $user . "]; /ip hotspot active remove [find user=" . $user . "]; /system scheduler remove [find name=" . $user . "]");
    /system scheduler add name=$user start-date=$dt interval="{{validity}}" on-event=$ev comment="Mikhmon expiry";
    {{LOCK_MAC_PART}}
  }
}
```
*(Untuk mode `ntf` / `ntfc`, aksi pada `$ev` diganti menjadi `/ip hotspot user set [find name=$user] limit-uptime=1s; /system scheduler remove [find name=$user]`.)*

---

### C. Snippet Tambahan (`Lock MAC` & `Webhook`)

1. **Lock MAC Address (`Lock User = Enable`):**
   ```routeros
   :local mac $"mac-address";
   /ip hotspot user set mac-address=$mac [find where name=$user]
   ```

2. **Webhook Penjualan Real-Time (`lib/webhook.php` -> `mikhmon_webhook_snippet()`):**
   Aktif otomatis apabila `MIKHMON_PUBLIC_URL` terisi pada `.env`. Jika webhook gagal (timeout / server offline), error dicatat ke log tetapi record di `/system/script` tetap tersimpan sebagai fallback:
   ```routeros
   :local mmwhurl "https://domain-anda/process/sales_webhook.php?session=NAMA_SESI";
   :local mmwhtoken "TOKEN_SESI_48_HEX";
   :do {
     /tool fetch url=$mmwhurl http-method=post http-header-field="X-Mikhmon-Token: $mmwhtoken" http-data=$sname check-certificate=no keep-result=no;
   } on-error={
     :log warning "mikhmon webhook gagal; record tetap ditulis ke script"
   };
   ```
   *(Parameter `check-certificate=no` hanya disertakan jika URL menggunakan `https://`.)*

---

### D. Scheduler Background Monitoring Profil Hotspot (`on-event`)

Dihasilkan oleh `mikhmon_v3_bgservice_script()` (`include/mikhmon_scripts.php`) untuk memeriksa masa aktif seluruh user pada profil tertentu secara berkala (interval acak ~2 menit):

```routeros
:local normdate do={
  :local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
  :local v $d;
  :if ([:pick $v 4 5] = "-") do={
    :local mn [:tonum [:pick $v 5 7]];
    :local m [:pick $mnames (($mn - 1) * 3) (($mn - 1) * 3 + 3)];
    :local rest "";
    :if ([:len $v] > 10) do={:set rest [:pick $v 10 [:len $v]]};
    :local mm [:pick $v 8 10];
    :local yy [:pick $v 0 4];
    :set v "$m/$mm/$yy$rest";
  };
  :return $v;
};
:local dateint do={
  :local mnames "janfebmaraprmayjunjulaugsepoctnovdec";
  :local days [:pick $d 4 6];
  :local month [:pick $d 0 3];
  :local year [:pick $d 7 11];
  :local monthint ([:find $mnames $month] / 3);
  :local monthnum ($monthint + 1);
  :if ([:len $monthnum] = 1) do={
    :local zero "0";
    :return [:tonum "$year$zero$monthnum$days"];
  } else={
    :return [:tonum "$year$monthnum$days"];
  };
};
:local timeint do={
  :local hours [:pick $t 0 2];
  :local minutes [:pick $t 3 5];
  :return ($hours * 60 + $minutes);
};
:local date [/system clock get date];
:local time [/system clock get time];
:local todaydate [$normdate d=$date];
:local today [$dateint d=$todaydate];
:local curtime [$timeint t=$time];
:foreach i in [/ip hotspot user find where profile="{{profile_name}}"] do={
  :local comment [/ip hotspot user get $i comment];
  :local name [/ip hotspot user get $i name];
  :local norm [$normdate d=$comment];
  :if ([:pick $norm 3] = "/" and [:pick $norm 6] = "/" and [:len $norm] >= 19) do={
    :local gettime [:pick $norm 12 20];
    :local expd [$dateint d=$norm];
    :local expt [$timeint t=$gettime];
    :if ($expd < $today or ($expd = $today and $expt < $curtime)) do={
      [ /ip hotspot user {{mode}} $i ];
      [ /ip hotspot active remove [find where user=$name] ];
    };
  };
};
```
*(Placeholder `{{mode}}` bernilai `remove` atau `set limit-uptime=1s`.)*

---

### E. Script Upgrade `on-login` Profil Lama (`OnloginUpgrader`)

`src/Router/OnloginUpgrader.php` menyisipkan snippet pencarian server (`serverLookupSnippet()`) ke profil Mikhmon lama tanpa menyentuh custom script lain, serta menyimpan backup JSON sebelum perubahan untuk rollback:

```routeros
:local mmsrv "";
:foreach mma in=[/ip/hotspot/active/find where user=$user] do={ :set mmsrv [ /ip/hotspot/active/get $mma server ] };
:if ($mmsrv = "") do={ :local mmu [/ip/hotspot/user/find where name=$user]; :if ([:len $mmu] > 0) do={ :set mmsrv [/ip/hotspot/user/get $mmu server] } };
:if ($mmsrv = "") do={ :local mmh [/ip/hotspot/find where interface=$interface]; :if ([:len $mmh] > 0) do={ :set mmsrv [/ip/hotspot/get $mmh name] } };
```

---

### F. Script Auto-Isolir PPPoE `on-up` (Khusus Branch `99barokah`)

Dihasilkan oleh `ppp/addpppprofile.php` dan `ppp/profilebyname.php` saat fitur isolir PPPoE diaktifkan pada PPP Profile:

```routeros
:local pengguna $"user";
:local date [/system clock get date];
:local time [/system clock get time];
:log info "User PPPoE $pengguna login pada $time tanggal $date";
{
  :if ([/system scheduler find name="exp-$pengguna"]="") do={
    /system scheduler add name="exp-$pengguna" interval={{ISOLIR_INTERVAL}} on-event="/ppp secret set profile=\"{{ISOLIR_PROFILE}}\" [find name=\$pengguna]; /ppp active remove [find name=\$pengguna]; :log warning \"User \$pengguna expired dan dipindah ke profile {{ISOLIR_PROFILE}}\"; /system scheduler remove [find name=\"exp-\$pengguna\"]";
    :log info "Scheduler auto expiry dibuat untuk user $pengguna ({{ISOLIR_INTERVAL}})";
  }
}
```
