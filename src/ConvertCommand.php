<?php

/*
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ConvertCommand extends Command
{
    protected function configure()
    {
        $this->setName('convert')
            ->setDescription('Converts Symfony XML configuration files to PHP DSL format')
            ->addArgument('source', InputArgument::REQUIRED, 'Source XML file or directory containing XML files')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target directory for converted PHP files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the changes without writing files')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing PHP files')
            ->addOption('skip-validation', null, InputOption::VALUE_NONE, 'Skip XML validation for faster processing')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude patterns (can be used multiple times)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        $dryRun = $input->getOption('dry-run');
        $overwrite = $input->getOption('overwrite');
        $skipValidation = $input->getOption('skip-validation');
        $excludePatterns = $input->getOption('exclude');
        
        $converter = new XmlToPhpConfigConverter();
        
        if ($skipValidation) {
            $converter->setSkipValidation(true);
        }

        $io->title('Symfony XML to PHP Converter');

        // Process files
        if (is_dir($source)) {
            $files = Finder::create()
                ->files()
                ->in($source)
                ->name('*.xml')
                ->notPath($excludePatterns);

            $filesArray = iterator_to_array($files);
            
            if (empty($filesArray)) {
                $io->warning('No XML files found in the specified directory.');
                return self::SUCCESS;
            }

            $io->section(sprintf('Found %d XML file(s) to convert', count($filesArray)));

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            foreach ($filesArray as $file) {
                try {
                    $this->processFile($io, $file, $target, $converter, $dryRun, $overwrite);
                    $successCount++;
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[$file->getRelativePathname()] = $e->getMessage();
                }
            }

            $io->section('Conversion Summary');
            $io->success(sprintf('Successfully converted: %d file(s)', $successCount));
            
            if ($failureCount > 0) {
                $io->error(sprintf('Failed to convert: %d file(s)', $failureCount));
                
                if (!empty($errors)) {
                    $io->section('Error Details');
                    foreach ($errors as $file => $error) {
                        $io->text(sprintf('<comment>%s:</comment> %s', $file, $error));
                    }
                }
                
                return self::FAILURE;
            }

            if ($dryRun) {
                $io->note('This was a dry run. No files were actually created.');
            }
        } elseif (is_file($source) && pathinfo($source, PATHINFO_EXTENSION) === 'xml') {
            try {
                $file = new SplFileInfo($source, dirname($source), basename($source));
                $this->processFile($io, $file, $target, $converter, $dryRun, $overwrite);
                
                if ($dryRun) {
                    $io->section('Preview of generated PHP file:');
                    $io->text($target ?: str_replace('.xml', '.php', $source));
                    $io->newLine();
                    $phpContent = $converter->convertFile($source);
                    $io->text($phpContent);
                } else {
                    $io->success('Conversion completed successfully.');
                }
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to convert: %s', $e->getMessage()));
                return self::FAILURE;
            }
        } else {
            $io->error('Source must be an XML file or a directory containing XML files.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Process a single XML file and convert it to PHP.
     */
    private function processFile(SymfonyStyle $io, SplFileInfo $file, ?string $targetDir, XmlToPhpConfigConverter $converter, bool $dryRun, bool $overwrite): void
    {
        $io->text(sprintf('Converting: %s', $file->getRelativePathname()));

        // Generate PHP content
        $phpContent = $converter->convertFile($file->getRealPath());

        // Determine the output path
        $phpFilename = $file->getBasename('.xml') . '.php';

        if ($targetDir !== null) {
            // Ensure target directory exists
            $outputDir = $targetDir . '/' . $file->getRelativePath();
            $phpPath = $outputDir . '/' . $phpFilename;
        } else {
            // Use the same directory as the source file
            $phpPath = $file->getPath() . '/' . $phpFilename;
        }

        if ($dryRun) {
            $io->text(sprintf('  Would create: %s', $phpPath));
            return;
        }

        // Check if file exists and overwrite option
        if (file_exists($phpPath) && !$overwrite) {
            throw new \RuntimeException(sprintf('File "%s" already exists. Use --overwrite to replace it.', $phpPath));
        }

        // Ensure directory exists
        $dir = dirname($phpPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Write the output file
        file_put_contents($phpPath, $phpContent);
        $io->text(sprintf('  Created: %s', $phpPath));
    }
}