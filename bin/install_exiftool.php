<?php
// bin/install_exiftool.php

$vendorDir = dirname(__DIR__) . '/vendor';
$exiftoolDir = $vendorDir . '/exiftool';

if (file_exists($exiftoolDir . '/exiftool.exe') || file_exists($exiftoolDir . '/exiftool')) {
    echo "ExifTool is already installed.\n";
    exit(0);
}

echo "Installing ExifTool...\n";

if (!is_dir($vendorDir)) {
    mkdir($vendorDir, 0755, true);
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $version = '13.59';
    $url = "https://downloads.sourceforge.net/project/exiftool/exiftool-{$version}_64.zip";
    $zipPath = $vendorDir . '/exiftool.zip';

    echo "Downloading ExifTool v{$version} for Windows...\n";
    
    $options = [
        "http" => [
            "header" => "User-Agent: PHP ExifTool Installer\r\n"
        ]
    ];
    $context = stream_context_create($options);
    
    $src = fopen($url, 'r', false, $context);
    if (!$src) {
        echo "Failed to download ExifTool from $url\n";
        exit(1);
    }
    
    $dest = fopen($zipPath, 'w');
    stream_copy_to_stream($src, $dest);
    fclose($src);
    fclose($dest);

    echo "Extracting ExifTool...\n";
    // Use tar to extract zip on Windows (available since Windows 10)
    $extractCmd = "tar -xf " . escapeshellarg($zipPath) . " -C " . escapeshellarg($vendorDir);
    
    // Bypass cmd.exe to avoid UNC path warnings
    $descriptorspec = array(
       0 => array("pipe", "r"),  // stdin
       1 => array("pipe", "w"),  // stdout
       2 => array("pipe", "w")   // stderr
    );
    $process = proc_open($extractCmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $return_var = proc_close($process);
    } else {
        $return_var = 1;
    }

    if ($return_var === 0) {
        unlink($zipPath);

        // Rename extracted folder
        $extractedDir = $vendorDir . '/exiftool-' . $version . '_64';
        if (is_dir($extractedDir)) {
            rename($extractedDir, $exiftoolDir);
            
            // Rename executable
            if (file_exists($exiftoolDir . '/exiftool(-k).exe')) {
                rename($exiftoolDir . '/exiftool(-k).exe', $exiftoolDir . '/exiftool.exe');
            }
            echo "ExifTool for Windows installed successfully to vendor/exiftool.\n";
        } else {
            echo "Failed to find extracted ExifTool folder at $extractedDir.\n";
            exit(1);
        }
    } else {
        echo "Failed to extract ExifTool zip archive. Error code: $return_var\nOutput: $stderr\n";
        exit(1);
    }
} else {
    // Basic Linux installation - assume we want the perl script version
    $version = '13.59';
    $url = "https://downloads.sourceforge.net/project/exiftool/Image-ExifTool-{$version}.tar.gz";
    $tarPath = $vendorDir . '/exiftool.tar.gz';

    echo "Downloading ExifTool v{$version} for Linux/macOS...\n";
    
    $options = [
        "http" => [
            "header" => "User-Agent: PHP ExifTool Installer\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $src = fopen($url, 'r', false, $context);
    if ($src) {
        $dest = fopen($tarPath, 'w');
        stream_copy_to_stream($src, $dest);
        fclose($src);
        fclose($dest);
        
        echo "Extracting ExifTool...\n";
        exec("tar -xzf " . escapeshellarg($tarPath) . " -C " . escapeshellarg($vendorDir));
        unlink($tarPath);
        
        $extractedDir = $vendorDir . '/Image-ExifTool-' . $version;
        if (is_dir($extractedDir)) {
            rename($extractedDir, $exiftoolDir);
            echo "ExifTool installed successfully to vendor/exiftool.\n";
        } else {
            echo "Failed to find extracted ExifTool folder.\n";
            exit(1);
        }
    } else {
        echo "Failed to download ExifTool.\n";
        exit(1);
    }
}
