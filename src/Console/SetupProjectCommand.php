<?php

namespace Devericlp\LaravelSetupConfig\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;


class SetupProjectCommand extends Command
{
    public $signature = 'dev-tools:setup';

    public $description = 'Command to setup development tools in your Laravel project';

    public function handle(): int
    {
         // Interactive multiselect prompt
        $choices = multiselect(
            label: 'Which tools would you like to set up?',
            options: [
                'larastan' => 'Larastan (PHP static analysis)',
                'pint'     => 'Laravel Pint (code formatter)',
                'husky'    => 'Husky (JavaScript pre-commit hook)',
            ],
            required: true,
            hint: 'Use space to select and press enter to confirm.'
        );

        // Run tool setups based on selection
        if (in_array('larastan', $choices)) {
            $this->setupLarastan();
        }

        if (in_array('pint', $choices)) {
            $this->setupPint();
        }

        if (in_array('husky', $choices)) {
            $this->setupHusky();
        }

        $this->info("\n✅ Setup completed successfully!");
        return self::SUCCESS;
    }

    private function setupLarastan(): void
    {
         $this->info('📦 Checking Larastan...');

         $composer = json_decode(file_get_contents($this->laravel->basePath('composer.json')), true);
         $larastan_not_installed = isset($composer['require-dev']['laravel/larastan']);

         if (! file_exists($this->laravel->basePath('vendor/larastan')) && ! $larastan_not_installed) {
            $this->comment('📦 Installing Larastan...');
            $this->runProcess(['composer', 'require', '--dev', 'larastan/larastan']);
        }

        $config_path = $this->laravel->basePath('phpstan.neon');

        if (file_exists($config_path)) {
            $should_replace = confirm(
                label: 'The phpstan.neon file already exists. Do you want to overwrite it with the default settings?',
                default: false
            );

            if (! $should_replace) {
                $this->line('⚠️ Skipping larastan configuration.');
                return;
            }
        }

        // Copy stub to project root
        $this->publishFile('phpstan.neon', true);

    }

    private function setupPint(): void
    {
        $this->info('🎨 Checking Laravel Pint...');

        $composer = json_decode(file_get_contents($this->laravel->basePath('composer.json')), true);
        $pint_not_installed = isset($composer['require-dev']['laravel/pint']);

        if (! file_exists($this->laravel->basePath('vendor/bin/pint')) && ! $pint_not_installed) {
            $this->comment('📦 Installing Laravel Pint...');
            $this->runProcess(['composer', 'require', '--dev', 'laravel/pint']);
        }

        $config_path = $this->laravel->basePath('pint.json');

        if (file_exists($config_path)) {
            $should_replace = confirm(
                label: 'The pint.json file already exists. Do you want to overwrite it with the default settings?',
                default: false
            );

            if (! $should_replace) {
                $this->line('⚠️ Skipping pint configuration.');
                return;
            }
        }

        // Copy stub to project root
        $this->publishFile('pint.json', true);
    }

    private function setupHusky(): void
    {
        $this->info('🐹 Checking Husky...');

        $base_path = $this->laravel->basePath();
        $package_json_path = $base_path . '/package.json';

        $package_json = json_decode(file_get_contents($package_json_path), true);

        $has_husky = isset($package_json['devDependencies']['husky']) ||
                     isset($package_json['dependencies']['husky']);

        if (! $has_husky) {
            $this->comment('📦 Installing Husky...');
            $this->runProcess(['npm', 'install', 'husky', '--save-dev']);
        }

        $this->comment('🔧 Running husky install...');
        $this->runProcess(['npx', 'husky', 'install']);

        $hook_path = $base_path . '/.husky/pre-commit';

        if (file_exists($hook_path)) {
            $should_replace = confirm(
                'A pre-commit hook already exists. Overwrite it?',
                default: false
            );

            if (! $should_replace) {
                $this->line('⚠️  Skipping pre-commit hook creation.');
                return;
            }
        }

        $this->comment('✍️  Creating pre-commit hook...');
        $this->publishFile('.husky/pre-commit', true);

        chmod($hook_path, 0755);

        $this->line('✅ Husky pre-commit hook created.');

        $prepare_script = $package_json['scripts']['prepare'] ?? '';

        if (stripos($prepare_script, 'husky install') === false) {
            $this->warn('👉 Consider adding `"prepare": "husky install"` to the scripts section in your package.json.');
        }
    }

    private function publishFile(string $relative_path, bool $force = false): void
    {
        $target = $this->laravel->basePath($relative_path);
        $stub_path = __DIR__ . "../Stubs/{$relative_path}";
        copy($stub_path, $target);
        $this->line("✅ {$relative_path} has been created from stub.");
    }

    private function runProcess(array $command): void
{
    (new Process($command, $this->laravel->basePath()))
        ->setTty(false)
        ->mustRun(function ($type, $buffer) {
            $this->output->write($buffer);
        });
}
}