<?php

define('MAX_WINDOW_SIZE', 262144);
define('BUFFER_SIZE', 512);

function compressFile($fileContent, $outputFile)
{
    $fileLength = strlen($fileContent);
    $compressedChunks = [];
    $currentPosition = 0;

    while ($currentPosition < $fileLength) {
        $longestMatchLength = 0;
        $matchOffset = 0;
        $startWindow = max(0, $currentPosition - MAX_WINDOW_SIZE);

        for ($searchPos = $startWindow; $searchPos < $currentPosition; $searchPos++) {
            $matchLength = 0;
            while (
                $matchLength < BUFFER_SIZE && $currentPosition + $matchLength < $fileLength &&
                $fileContent[$searchPos + $matchLength] === $fileContent[$currentPosition + $matchLength]) {
                $matchLength++;
            }

            if ($matchLength > $longestMatchLength) {
                $longestMatchLength = $matchLength;
                $matchOffset = $currentPosition - $searchPos;
            }
        }

        $nextChar = $currentPosition + $longestMatchLength < $fileLength ? $fileContent[$currentPosition + $longestMatchLength] : '';
        $compressedChunks[] = [$matchOffset, $longestMatchLength, $nextChar];

        $currentPosition += $longestMatchLength + 1;
    }

    file_put_contents($outputFile, serialize($compressedChunks));
}

function decompressFile($inputFile, $outputFile)
{
    $compressedChunks = unserialize(file_get_contents($inputFile));
    $decompressedContent = '';

    foreach ($compressedChunks as [$offset, $length, $nextChar]) {
        $start = strlen($decompressedContent) - $offset;
        for ($i = 0; $i < $length; $i++) {
            $decompressedContent .= $decompressedContent[$start + $i];
        }
        $decompressedContent .= $nextChar;
    }

    file_put_contents($outputFile, $decompressedContent);
}

$startTime = microtime(true);
$memoryBefore = memory_get_usage();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['file']['tmp_name'];
    $action = isset($_POST['action']) ? $_POST['action'] : 'compress';

    $fileContent = file_get_contents($uploadedFile);

    if ($action === 'compress') {
        $outputFile = 'compressed.lz77';

        compressFile($fileContent, $outputFile);

        echo "<h3>Файл успешно сжат!</h3>";
        echo "<p>Сжатый файл сохранен как <strong>$outputFile</strong>.</p>";
    }
    elseif ($action === 'decompress') {
        $outputFile = 'decompressed.txt';

        decompressFile($uploadedFile, $outputFile);

        echo "<h3>Файл успешно разжат!</h3>";
        echo "<p>Разжатый файл сохранен как <strong>$outputFile</strong>.</p>";
    }

    $endTime = microtime(true);
    $memoryAfter = memory_get_usage();

    $executionTime = $endTime - $startTime;
    echo "<p>Время выполнения: " . round($executionTime, 4) . " секунд.</p>";

    $memoryUsage = $memoryAfter - $memoryBefore;
    echo "<p>Использованная память: " . number_format($memoryUsage) . " байт.</p>";
} else {
    echo "<h3>Ошибка при загрузке файла!</h3>";
}
?>