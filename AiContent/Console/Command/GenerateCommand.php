<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Taimoor\AiContent\Model\ContentGenerator;

/**
 * bin/magento taimoor:ai-content:generate SKU [--dry-run] [--overwrite]
 */
class GenerateCommand extends Command
{
    public function __construct(
        private readonly State $state,
        private readonly ContentGenerator $generator,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('taimoor:ai-content:generate')
            ->setDescription('Generate product description and SEO meta tags with AI')
            ->addArgument('sku', InputArgument::REQUIRED, 'Product SKU')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the result without saving')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Replace existing content');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // area code was already set
        }

        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->generator->generateBySku(
                (string) $input->getArgument('sku'),
                $dryRun,
                $input->getOption('overwrite') ? true : null
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>%s (%s)</info>', $result['name'], $result['sku']));

        foreach ($result['updated'] as $field => $value) {
            $output->writeln(sprintf("\n<comment>%s</comment>\n%s", $field, $value));
        }

        if ($result['skipped']) {
            $output->writeln("\nSkipped (already has content, use --overwrite): " . implode(', ', $result['skipped']));
        }

        $output->writeln($dryRun ? "\nDry run: nothing was saved." : "\nSaved.");

        return Command::SUCCESS;
    }
}
