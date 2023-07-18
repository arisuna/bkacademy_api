<?php

/**
 * Slug component
 *
 * @package Phalcon\Utils
 */
class RelodayMigration extends \Phalcon\Migrations
{
    /**
     * @param array $options
     */
    public function runMigration($options){

        $optionStack = new OptionStack();
        $listTables = new ListTablesIterator();
        $optionStack->setOptions($options);
        $optionStack->setDefaultOption('verbose', false);

        // Define versioning type to be used
        if (isset($options['tsBased']) && $optionStack->getOption('tsBased') === true) {
            VersionCollection::setType(VersionCollection::TYPE_TIMESTAMPED);
        } else {
            VersionCollection::setType(VersionCollection::TYPE_INCREMENTAL);
        }

        $migrationsDir = rtrim($optionStack->getOption('migrationsDir'), '\\/');
        if (!file_exists($migrationsDir)) {
            throw new ModelException('Migrations directory was not found.');
        }

        if (!$optionStack->getOption('config') instanceof Config) {
            throw new ModelException('Internal error. Config should be an instance of ' . Config::class);
        }

        // Init ModelMigration
        if (!isset($optionStack->getOption('config')->database)) {
            throw new ScriptException('Cannot load database configuration');
        }

        $finalVersion = null;
        if (isset($options['version']) && $optionStack->getOption('version') !== null) {
            $finalVersion = VersionCollection::createItem($options['version']);
        }

        $optionStack->setOption('tableName', $options['tableName'], '@');

        $versionItems = ModelMigration::scanForVersions($migrationsDir);

        if (!isset($versionItems[0])) {
            if (php_sapi_name() == 'cli') {
                fwrite(STDERR, PHP_EOL . 'Migrations were not found at ' . $migrationsDir . PHP_EOL);
                exit;
            } else {
                throw new ModelException('Migrations were not found at ' . $migrationsDir);
            }
        }

        // Set default final version
        if ($finalVersion === null) {
            $finalVersion = VersionCollection::maximum($versionItems);
        }

        ModelMigration::setup($optionStack->getOption('config')->database, $optionStack->getOption('verbose'));
        ModelMigration::setMigrationPath($migrationsDir);
        self::connectionSetup($optionStack->getOptions());
        $initialVersion = self::getCurrentVersion($optionStack->getOptions());
        $completedVersions = self::getCompletedVersions($optionStack->getOptions());

        // Everything is up to date
        if ($initialVersion->getStamp() === $finalVersion->getStamp()) {
            print Color::info('Everything is up to date');
            exit(0);
        }

        $direction = ModelMigration::DIRECTION_FORWARD;
        if ($finalVersion->getStamp() < $initialVersion->getStamp()) {
            $direction = ModelMigration::DIRECTION_BACK;
        }

        if (ModelMigration::DIRECTION_FORWARD === $direction) {
            // If we migrate up, we should go from the beginning to run some migrations which may have been missed
            $versionItemsTmp = VersionCollection::sortAsc(array_merge($versionItems, [$initialVersion]));
            $initialVersion = $versionItemsTmp[0];
        } else {
            // If we migrate downs, we should go from the last migration to revert some migrations which may have been missed
            $versionItemsTmp = VersionCollection::sortDesc(array_merge($versionItems, [$initialVersion]));
            $initialVersion = $versionItemsTmp[0];
        }

        // Run migration
        $versionsBetween = VersionCollection::between($initialVersion, $finalVersion, $versionItems);
        $prefix = $optionStack->getPrefixOption($optionStack->getOption('tableName'));
        foreach ($versionsBetween as $versionItem) {

            // If we are rolling back, we skip migrating when initialVersion is the same as current
            if ($initialVersion->getVersion() === $versionItem->getVersion() && ModelMigration::DIRECTION_BACK === $direction) {
                continue;
            }

            if ((ModelMigration::DIRECTION_FORWARD === $direction) && isset($completedVersions[(string)$versionItem])) {
                print Color::info('Version ' . (string)$versionItem . ' was already applied');
                continue;
            } elseif ((ModelMigration::DIRECTION_BACK === $direction) && !isset($completedVersions[(string)$initialVersion])) {
                print Color::info('Version ' . (string)$initialVersion . ' was already rolled back');
                $initialVersion = $versionItem;
                continue;
            }

            if ($initialVersion->getVersion() === $finalVersion->getVersion() && ModelMigration::DIRECTION_BACK === $direction) {
                break;
            }

            $migrationStartTime = date("Y-m-d H:i:s");

            // Directory depends on Forward or Back Migration
            $iterator = new DirectoryIterator(
                $migrationsDir . DIRECTORY_SEPARATOR . (ModelMigration::DIRECTION_BACK === $direction ? $initialVersion->getVersion() : $versionItem->getVersion())
            );

            if ($optionStack->getOption('tableName') === '@') {
                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile() || 0 !== strcasecmp($fileInfo->getExtension(), 'php')) {
                        continue;
                    }
                    ModelMigration::migrate($initialVersion, $versionItem, $fileInfo->getBasename('.php'));
                }
            } else {
                if (!empty($prefix)) {
                    $optionStack->setOption('tableName', $listTables->listTablesForPrefix($prefix, $iterator));
                }

                $tables =  explode(',', $optionStack->getOption('tableName'));
                foreach ($tables as $tableName) {
                    ModelMigration::migrate($initialVersion, $versionItem, $tableName);
                }
            }

            if (ModelMigration::DIRECTION_FORWARD == $direction) {
                self::addCurrentVersion($optionStack->getOptions(), (string)$versionItem, $migrationStartTime);
                print Color::success('Version ' . $versionItem . ' was successfully migrated');
            } else {
                self::removeCurrentVersion($optionStack->getOptions(), (string)$initialVersion);
                print Color::success('Version ' . $initialVersion . ' was successfully rolled back');
            }

            $initialVersion = $versionItem;
        }
    }

    /**
     * Initialize migrations log storage
     *
     * @param array $options Applications options
     * @throws DbException
     */
    private static function connectionSetup($options)
    {
        if (self::$_storage) {
            return;
        }

        if (isset($options['migrationsInDb']) && (bool)$options['migrationsInDb']) {
            /** @var Config $database */
            $database = $options['config']['database'];

            if (!isset($database->adapter)) {
                throw new DbException('Unspecified database Adapter in your configuration!');
            }

            $adapter = '\\Phalcon\\Db\\Adapter\\Pdo\\' . $database->adapter;

            if (!class_exists($adapter)) {
                throw new DbException('Invalid database Adapter!');
            }

            $configArray = $database->toArray();
            unset($configArray['adapter']);
            self::$_storage = new $adapter($configArray);

            if ($database->adapter === 'Mysql') {
                self::$_storage->query('SET FOREIGN_KEY_CHECKS=0');
            }

            if (!self::$_storage->tableExists(self::MIGRATION_LOG_TABLE)) {
                self::$_storage->createTable(self::MIGRATION_LOG_TABLE, null, [
                    'columns' => [
                        new Column(
                            'version',
                            [
                                'type' => Column::TYPE_VARCHAR,
                                'size' => 255,
                                'notNull' => true,
                            ]
                        ),
                        new Column(
                            'start_time',
                            [
                                'type' => Column::TYPE_TIMESTAMP,
                                'notNull' => true,
                                'default' => 'CURRENT_TIMESTAMP',
                            ]
                        ),
                        new Column(
                            'end_time',
                            [
                                'type' => Column::TYPE_TIMESTAMP,
                                'notNull' => true,
                            ]
                        )
                    ],
                    'indexes' => [
                        new Index('idx_' . self::MIGRATION_LOG_TABLE . '_version', ['version'])
                    ]
                ]);
            }

        } else {
            $database = $options['config']['database'];
            if (empty($options['directory'])) {
                $path = defined('BASE_PATH') ? BASE_PATH : defined('APP_PATH') ? dirname(APP_PATH) : '';
                $path = rtrim($path, '\\/') . '/.phalcon';
            } else {
                $path = rtrim($options['directory'], '\\/') . '/.phalcon';
            }
            if (!is_dir($path) && !is_writable(dirname($path))) {
                throw new \RuntimeException("Unable to write '{$path}' directory. Permission denied");
            }
            if (is_file($path)) {
                unlink($path);
                mkdir($path);
                chmod($path, 0775);
            } elseif (!is_dir($path)) {
                mkdir($path);
                chmod($path, 0775);
            }

            self::$_storage = $path . '/migration-version-'.$database;

            if (!file_exists(self::$_storage)) {
                if (!is_writable($path)) {
                    throw new \RuntimeException("Unable to write '" . self::$_storage . "' file. Permission denied");
                }
                touch(self::$_storage);
            }
        }
    }
}
