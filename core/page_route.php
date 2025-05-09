<?php

function forbPHP()
{
    return;
}

function e403()
{
    header("HTTP/1.1 403 Forbidden");
    echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>403 Forbidden</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * {
                    box-sizing: border-box;
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                }
    
                body {
                    margin: 0;
                    padding: 0;
                    background: linear-gradient(135deg, #2c3e50, #3498db);
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    text-align: center;
                }
    
                .container {
                    max-width: 500px;
                    padding: 40px;
                    background-color: rgba(0, 0, 0, 0.4);
                    border-radius: 12px;
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.6);
                }
    
                h1 {
                    font-size: 72px;
                    margin: 0;
                    color: #e74c3c;
                }
    
                h2 {
                    margin-top: 10px;
                    font-size: 24px;
                    color: #ecf0f1;
                }
    
                p {
                    font-size: 16px;
                    margin: 20px 0;
                    color: #bdc3c7;
                }
    
                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background-color: #e67e22;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    transition: background 0.3s ease;
                }
    
                a:hover {
                    background-color: #d35400;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>403</h1>
                <h2>Forbidden</h2>
                <p>Akses ke halaman ini ditolak. Anda tidak memiliki izin untuk melihat konten ini.</p>
                <a href="javascript:history.back()">Kembali</a>
            </div>
        </body>
        </html>';
    exit();
}

function e404()
{
    header("HTTP/1.1 404 Not Found");
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>404 Not Found</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * {
                box-sizing: border-box;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            }

            body {
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                text-align: center;
            }

            .container {
                max-width: 500px;
                padding: 40px;
                background-color: rgba(0, 0, 0, 0.4);
                border-radius: 12px;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.6);
            }

            h1 {
                font-size: 72px;
                margin: 0;
                color: #e74c3c;
            }

            h2 {
                margin-top: 10px;
                font-size: 24px;
                color: #ecf0f1;
            }

            p {
                font-size: 16px;
                margin: 20px 0;
                color: #bdc3c7;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #2ecc71;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                transition: background 0.3s ease;
            }

            a:hover {
                background-color: #27ae60;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>404</h1>
            <h2>Page Not Found</h2>
            <p>Oops! Halaman yang Anda cari tidak ditemukan atau mungkin sudah dipindahkan.</p>
            <a href="javascript:history.back()">Kembali ke halaman sebelumnya</a>
        </div>
    </body>
    </html>';
    exit();
}

function decode($data)
{
    try {
        // Step 1: Base64 decode
        $decoded = base64_decode($data);

        // Step 2: XOR each character with 10
        $xorResult = '';
        for ($i = 0; $i < strlen($decoded); $i++) {
            $xorResult .= chr(ord($decoded[$i]) ^ 10);
        }

        // Step 3: Double base64 decode
        $original = base64_decode(base64_decode($xorResult));

        return $original;
    } catch (Exception $e) {
        error_log("Decode error: " . $e->getMessage());
        return null;
    }
}
