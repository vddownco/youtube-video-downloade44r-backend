<?php
echo "<pre>";
echo "Testing PATH version: ";
echo shell_exec('yt-dlp --version 2>&1');

echo "\nTesting absolute path: ";
echo shell_exec('C:\Python313\Scripts\yt-dlp.exe --version 2>&1');