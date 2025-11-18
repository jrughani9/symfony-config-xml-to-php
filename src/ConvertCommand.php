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

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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
                    $this->processFile($io, $file, $target, $converter, $dryRun, $overwrite, $skipValidation);
                    $successCount++;
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[$file->getRelativePathname()] = $e->getMessage();
                }
            }

            $io->section('Conversion Summary');
            $io->success(sprintf('Successfully converted: %d file(s)', $successCount));

            if ($failureCount > 0) {
                $io->warning(sprintf('Failed to convert: %d file(s)', $failureCount));

                if (!empty($errors)) {
                    $io->section('Error Details');
                    foreach ($errors as $file => $error) {
                        $io->text(sprintf('<comment>%s:</comment> %s', $file, $error));
                    }
                }

                return self::SUCCESS;
            }

            if ($converter->getWarningCollector()->hasWarnings()) {
                $io->section('Warnings');
                $warnings = $converter->getWarningCollector()->getFormattedWarnings();
                foreach ($warnings as $warning) {
                    $io->warning($warning);
                }
            }

            if ($dryRun) {
                $io->note('This was a dry run. No files were actually created.');
            }
        } elseif (is_file($source) && pathinfo($source, PATHINFO_EXTENSION) === 'xml') {
            try {
                $file = new SplFileInfo($source, dirname($source), basename($source));
                $this->processFile($io, $file, $target, $converter, $dryRun, $overwrite, $skipValidation);

                if ($dryRun) {
                    $io->section('Preview of generated PHP file:');
                    $io->text($target ?: str_replace('.xml', '.php', $source));
                    $io->newLine();
                    $converter->getWarningCollector()->clear();
                    $phpContent = $converter->convertFile($source);
                    $io->text($phpContent);
                } else {
                    $io->success('Conversion completed successfully.');
                }

                if ($converter->getWarningCollector()->hasWarnings()) {
                    $io->section('Warnings');
                    $warnings = $converter->getWarningCollector()->getFormattedWarnings();
                    foreach ($warnings as $warning) {
                        $io->warning($warning);
                    }
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
    private function processFile(SymfonyStyle $io, SplFileInfo $file, ?string $targetDir, XmlToPhpConfigConverter $converter, bool $dryRun, bool $overwrite, bool $skipValidation): void
    {
        $io->text(sprintf('Converting: %s', $file->getRelativePathname()));

        // Generate PHP content
        $phpContent = $converter->convertFile($file->getRealPath());

        // Determine the output path
        $phpFilename = $file->getBasename('.xml').'.php';

        if ($targetDir !== null) {
            // Build the output directory path
            $relativePath = $file->getRelativePath();
            if ($relativePath !== '') {
                $outputDir = $targetDir.'/'.$relativePath;
                $phpPath = $outputDir.'/'.$phpFilename;
            } else {
                $phpPath = $targetDir.'/'.$phpFilename;
            }
        } else {
            // Use the same directory as the source file
            $phpPath = $file->getPath().'/'.$phpFilename;
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

        $this->validateFile($io, $file->getRealPath(), $phpPath, $skipValidation);
    }

    /**
     * Validate that the converted PHP file produces the same container as the original XML file.
     */
    private function validateFile(SymfonyStyle $io, string $xmlFile, string $phpFile, bool $skipValidation): bool
    {
        if ($skipValidation) {
            return true;
        }

        try {
            $xmlContainer = new ContainerBuilder();
            $xmlLoader = new XmlFileLoader($xmlContainer, new FileLocator());
            $xmlLoader->load(realpath($xmlFile));
        } catch (LoaderLoadException|InvalidArgumentException|LogicException $e) {
            $io->text(sprintf('  <comment>Skipping validation</comment> - XML file has errors: %s', $e->getMessage()));
            return false;
        }

        try {
            $phpContainer = new ContainerBuilder();
            $phpLoader = new PhpFileLoader($phpContainer, new FileLocator());
            $phpLoader->load(realpath($phpFile));
        } catch (\Throwable $e) {
            $io->text(sprintf('  <error>Validation failed</error> - PHP file cannot be loaded: %s', $e->getMessage()));
            return false;
        }

        $xmlDump = new XmlDumper($xmlContainer)->dump();
        $phpDump = new XmlDumper($phpContainer)->dump();

        if ($xmlDump === $phpDump) {
            $io->text('  <info>✓ Validation passed</info>');
            return true;
        }

        $differ = new Differ(new UnifiedDiffOutputBuilder());
        $diff = $differ->diff($xmlDump, $phpDump);
        $io->text('  <error>✗ Validation failed</error> - The XML and PHP dumps are not equal');
        
        if ($io->isVerbose()) {
            $io->newLine();
            $io->text('  Diff:');
            $replace = [
                '~^(---.*?)$~m' => '<fg=yellow>$1</>',
                '~^(\+\+\+.*?)$~m' => '<fg=yellow>$1</>',
                '~^(@@.*?@@)$~m' => '<fg=cyan>$1</>',
                '~^(\-.*?)$~m' => '<fg=red>$1</>',
                '~^(\+.*?)$~m' => '<fg=green>$1</>',
            ];
            $coloredDiff = preg_replace(array_keys($replace), array_values($replace), $diff);
            $io->text('  ' . str_replace("\n", "\n  ", $coloredDiff));
        }

        return false;
    }
}
