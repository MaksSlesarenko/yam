<?php

namespace Yam\Migrations\Command;

use Yam\Migrations\Configuration\Configuration;
use Yam\Migrations\SchemaConverter;
use Doctrine\DBAL\Schema\SchemaConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class DiffCommand extends GenerateCommand
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
        parent::configure();

        $this
            ->setName($this->getCommandPrefix() . 'diff')
            ->setDescription('Generate a migration by comparing your current database to your mapping information.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a migration by comparing your current database to your mapping information:

    <info>%command.full_name%</info>
EOT
            )
            ->addOption('filter-expression', null, InputOption::VALUE_OPTIONAL, 'Tables which are filtered by Regular Expression.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->getMigrationConfiguration($input, $output);

        $conn = $configuration->getConnection();

        $platform = $conn->getDatabasePlatform();

        $schemaFile = $this->getSchemaPath($configuration, $input->getOption('schema'));

        if (!file_exists($schemaFile)) {
            $output->writeln(sprintf('<error>File "%s" not found.</error>', $schemaFile));
            return;
        }
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength($conn->getDatabasePlatform()->getMaxIdentifierLength());

        $params = $conn->getParams();
        if (isset($params['defaultTableOptions'])) {
            $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);
        }


        $schemaArray = Yaml::parse($schemaFile);

        $toSchema = SchemaConverter::toSchema($schemaArray);
        $fromSchema = $conn->getSchemaManager()->createSchema();

        $up = $fromSchema->getMigrateToSql($toSchema, $platform);
        $down = $fromSchema->getMigrateFromSql($toSchema, $platform);

        $up = $this->buildCodeFromSql($configuration, $up);
        $down = $this->buildCodeFromSql($configuration, $down);

        if ( ! $up && ! $down) {
            $output->writeln('<error>No changes detected in your mapping information.</error>');

            return;
        }

        $version = date('YmdHis');
        $path = $this->generateMigration($configuration, $input, $version, $up, $down);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
    }

    private function buildCodeFromSql(Configuration $configuration, array $sql)
    {
        $code = array();
        foreach ($sql as $query) {
            if (strpos($query, $configuration->getMigrationsTableName()) !== false) {
                continue;
            }
            $query = str_replace('"', '\"', $query);

            $code[] = "\$this->addSql(\"$query\");";
        }
        if ($code) {
            $currentPlatform = $configuration->getConnection()->getDatabasePlatform()->getName();
            array_unshift(
                $code,
                "\$this->abortIf(\$this->connection->getDatabasePlatform()->getName() != \"$currentPlatform\", \"Migration can only be executed safely on '$currentPlatform'.\");",
                ""
            );
        }

        return implode("\n", $code);
    }
}
