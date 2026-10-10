<?php

// Deploy changed files to the shared host over FTP (git on the server
// cannot reach GitHub). Uploads everything that changed between the
// server's HEAD (3596500) and local HEAD, deletes removed files, and
// never touches .env / database.sqlite / storage (live server data).

$host = 'ftp.kushiaraseed.com';
$user = 'nazmul@agor.kushiaraseed.com';
$pass = 'NxvyMdLp$h%V_2^O';

chdir(__DIR__);

$diff = shell_exec('git diff --name-only --diff-filter=AM 3596500 HEAD');
$uploads = array_values(array_filter(explode("\n", trim($diff))));
$deletes = array_values(array_filter(explode("\n", trim((string) shell_exec('git diff --name-only --diff-filter=D 3596500 HEAD')))));

// Never upload live-data paths even if they ever show up in a diff.
$uploads = array_filter($uploads, fn ($f) => ! preg_match('#^(\.env|storage/|database/.*\.sqlite)$#', $f));

$conn = @ftp_ssl_connect($host, 21, 60) ?: @ftp_connect($host, 21, 60);
if (! $conn) {
    fwrite(STDERR, "FTP connect failed\n");
    exit(1);
}
if (! @ftp_login($conn, $user, $pass)) {
    fwrite(STDERR, "FTP login failed\n");
    exit(1);
}
ftp_pasv($conn, true);

$ok = 0;
$fail = [];

$ensureDir = function (string $remoteDir) use ($conn): void {
    $parts = explode('/', trim($remoteDir, '/'));
    $path = '';
    foreach ($parts as $part) {
        $path .= '/'.$part;
        @ftp_mkdir($conn, $path);
    }
};

foreach ($uploads as $file) {
    $remote = '/'.str_replace('\\', '/', $file);
    $ensureDir(dirname($remote));
    if (@ftp_put($conn, $remote, $file, FTP_BINARY)) {
        $ok++;
        echo "UP  $file\n";
    } else {
        $fail[] = $file;
        echo "ERR $file\n";
    }
}

foreach ($deletes as $file) {
    $remote = '/'.str_replace('\\', '/', $file);
    if (@ftp_delete($conn, $remote)) {
        echo "DEL $file\n";
    } else {
        echo "DEL-FAIL $file (may not exist)\n";
    }
}

ftp_close($conn);

echo "\nUploaded: $ok / ".count($uploads)."\n";
if ($fail !== []) {
    echo "FAILED:\n - ".implode("\n - ", $fail)."\n";
    exit(1);
}
echo "All files uploaded.\n";
