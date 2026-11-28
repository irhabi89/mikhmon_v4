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
if (session_status() === PHP_SESSION_NONE) session_start();


?>

<div style="padding-top: 5%;"  class="login-box">
  <div class="card">
    <div class="card-header">
      <h3><?= $_please_login ?></h3>
    </div>
    <div class="card-body">
      <div class="text-center pd-5">
        <img src="img/favicon.png" alt="MIKHMON Logo">
      </div>
      <div  class="text-center">
      <span style="font-size: 25px; margin: 10px;">MIKHMON</span>
      <div class="mm-muted" style="font-size: 12px; letter-spacing: .04em;">GHAIB.NET Edition</div>
      </div>
      <center>
      <form autocomplete="off" action="" method="post">
      <table class="table" style="width:90%">
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; height: 35px; font-size: 16px;" class="form-control" type="text" name="user" id="_username" placeholder="Username" required="1" autofocus>
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; height: 35px; font-size: 16px;" class="form-control" type="password" name="pass" placeholder="Password" required="1">
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <input style="width: 100%; margin-top:20px; height: 35px; font-weight: bold; font-size: 17px;" class="btn-login bg-primary pointer" type="submit" name="login" value="Login">
          </td>
        </tr>
        <tr>
          <td class="align-middle text-center">
            <?= $error; ?>
          </td>
        </tr>
      </table>
      </form>
      </center>
      <div class="text-center mm-muted" style="font-size: 10.5px; margin-top: 12px; line-height: 1.4;">
        Modified by <b>GHAIB.NET</b> · Developer <b>quiqxiq</b><br>
        <a href="https://www.ghaib.net/" target="_blank" rel="noopener">www.ghaib.net</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
