<?php

class Composer
{
    private string $composerPath;
    private string $phpBinary;

    public function __construct(string $composerPath = __DIR__ . '/composer.phar', string $phpBinary = 'php')
    {
        $this->composerPath = $composerPath;
        $this->phpBinary = $phpBinary;
    }

    /**
     * Führt einen Composer-Befehl aus und gibt die komplette Ausgabe zurück.
     */
    public function run(string $command): array
    {
        $fullCommand = escapeshellcmd("{$this->phpBinary} {$this->composerPath} {$command}");

        $descriptorSpec = [
            0 => ["pipe", "r"],   // STDIN
            1 => ["pipe", "w"],   // STDOUT
            2 => ["pipe", "w"]    // STDERR
        ];

        $process = proc_open($fullCommand, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => 'Fehler: Composer-Prozess konnte nicht gestartet werden.'
            ];
        }

        // Ausgabe lesen
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        // Pipes schließen
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        return [
            'success' => $returnCode === 0,
            'output' => trim($stdout . "\n" . $stderr),
            'exitCode' => $returnCode
        ];
    }
}
