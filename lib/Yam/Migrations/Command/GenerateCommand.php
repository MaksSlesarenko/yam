<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\Configuration\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class GenerateCommand extends AbstractCommand
{

    private static $_template =
            '<?php

namespace <namespace>;

use Yam\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version<version> extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        <up>
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        <down>
    }
}
';

    protected function configure()
    {
        $this
                ->setName('yam:generate')
                ->setDescription('Generate a blank migration class.')
                ->addOption('editor-cmd', null, InputOption::VALUE_OPTIONAL, 'Open file with this command upon creation.')
                ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a blank migration class:

    <info>%command.full_name%</info>

You can optionally specify a <comment>--editor-cmd</comment> option to open the generated file in your favorite editor:

    <info>%command.full_name% --editor-cmd=mate</info>
EOT
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $version = date('YmdHis');
        $path = $this->generateMigration($configuration, $input, $version);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>"', $path));
    }

    protected function generateMigration(Configuration $configuration, InputInterface $input, $version, $up = null, $down = null)
    {
        $placeHolders = array(
            '<namespace>',
            '<version>',
            '<up>',
            '<down>'
        );
        $replacements = array(
            $configuration->getMigrationsNamespace(),
            $version,
            $up ? "        " . implode("\n        ", explode("\n", $up)) : null,
            $down ? "        " . implode("\n        ", explode("\n", $down)) : null
        );
        $code = str_replace($placeHolders, $replacements, self::$_template);
        $dir = $configuration->getMigrationsDirectory();
        $dir = $dir ? $dir : getcwd();
        $dir = rtrim($dir, '/');
        $path = $dir . '/Version' . $version . '.php';

        if ( ! file_exists($dir)) {
            throw new \InvalidArgumentException(sprintf('Migrations directory "%s" does not exist.', $dir));
        }

        file_put_contents($path, $code);

        if ($editorCmd = $input->getOption('editor-cmd')) {
            shell_exec($editorCmd . ' ' . escapeshellarg($path));
        }

        return $path;
    }
}
