<?php
// src/Services/PdfParserService.php

namespace App\Services;

class PdfParserService
{
    public function parseTimetable($filePath)
    {
        $pythonScript = __DIR__ . '/../../python/timetable_parser.py';

        // Command to execute
        $cmd = "python " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath);

        $descriptorspec = [
           0 => ["pipe", "r"],  // stdin
           1 => ["pipe", "w"],  // stdout
           2 => ["pipe", "w"]   // stderr
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Set stream blocking mode to non-blocking
            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);

            $output = "";
            $errorOutput = "";
            $startTime = time();
            $timeout = 60; // 60 seconds timeout

            do {
                $output .= stream_get_contents($pipes[1]);
                $errorOutput .= stream_get_contents($pipes[2]);
                
                $status = proc_get_status($process);
                
                if (time() - $startTime > $timeout) {
                    // Timeout occurred
                    proc_terminate($process);
                    return ["error" => "Process timed out after $timeout seconds."];
                }

                usleep(100000); // Sleep 100ms to reduce CPU usage
            } while ($status['running']);

            // Get remaining output
            $output .= stream_get_contents($pipes[1]);
            $errorOutput .= stream_get_contents($pipes[2]);

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $exitCode = proc_close($process);

            // If Python script failed (non-zero exit code)
            if ($exitCode !== 0) {
                return [
                    "error" => "Python script failed with exit code $exitCode",
                    "details" => $errorOutput ?: "No error output captured."
                ];
            }

            // Decode JSON output
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    "error" => "JSON Decode Error: " . json_last_error_msg(),
                    "raw_output" => $output,
                    "stderr" => $errorOutput
                ];
            }
            
            return $data;
        } else {
             return ["error" => "Failed to launch Python process."];
        }
    }

}
