<?php namespace ErnestoVargas\Generators\Commands;

use ErnestoVargas\Generators\Utilities\RuleProcessor;
use ErnestoVargas\Generators\Utilities\Util;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Console\GeneratorCommand;
use Philo\Blade\Blade;

class MakeDingoCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:dingo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build Dingo API controllers, requests and transformers from DB schema.';

    /**
     * Rule processor class instance.
     *
     * @var
     */
    protected $ruleProcessor;

    /**
     * Rules for columns that go into the guarded list.
     *
     * @var array
     */
    protected $guardedRules = "equals:id"; //['ends' => ['_id', 'ids'], 'equals' => ['id']];

    /**
     * Rules for columns that go into the fillable list.
     *
     * @var array
     */
    protected $fillableRules = '';

    /**
     * Rules for columns that go into the fillable list.
     *
     * @var array
     */
    protected $columnsRules = 'ends:_id';

    /**
     * Rules for columns that set whether the timestamps property is set to true/false.
     *
     * @var array
     */
    protected $timestampRules = 'ends:_at'; //['ends' => ['_at']];

    /**
     * Contains the template stub for get function
     * @var string
     */
    protected $fkFunction;

    /**
     * Path to blade views
     * @var string
     */
    protected $views = __DIR__ . '/../views';

    /**
     * Path to cache blade views creation
     * @var string
     */
    protected $cache = '/tmp';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // Create the Blade
        $this->blade = new Blade($this->views, $this->cache);

        // create rule processor
        $this->ruleProcessor = new RuleProcessor();

        $tables = $this->getSchemaTables();

        foreach ($tables as $table) {
            $this->generateTable($table->name);
        }
    }

    /**
     * Get schema tables.
     *
     * @return array
     */
    protected function getSchemaTables()
    {
        $tables = \DB::select("SELECT table_name AS `name` FROM information_schema.tables WHERE table_schema = DATABASE()");

        return $tables;
    }

    /**
     * Generate a model file from a database table.
     *
     * @param $table
     */
    protected function generateTable($table)
    {
        $ignoreTable = $this->option("ignore");

        if ($this->option("ignoresystem")) {
            $ignoreSystem = "users,permissions,permission_role,roles,role_user,users,migrations,password_resets";

            if (is_string($ignoreTable)) {
                $ignoreTable.=",".$ignoreSystem;
            } else {
                $ignoreTable = $ignoreSystem;
            }
        }

        // if we have ignore tables, we need to find all the posibilites
        if (is_string($ignoreTable) && preg_match("/^".$table."|^".$table.",|,".$table.",|,".$table."$/", $ignoreTable)) {
            $this->info($table." is ignored");
            return;
        }

        $class = Util::convertTableNameToClassName($table);

        $this_classes = ['Controller','Request','Transformer'];
        foreach ($this_classes as $tclass) {
            $path  = app_path('Api/'.$tclass.'s/'. $class . $tclass.'.php');

            if ($this->files->exists($path)) {
                return $this->error('Dingo API: '.$table.$tclass.' already exists!');
            }

            $this->makeDirectory($path);

            $this->files->put($path, "<?php \n\n" . $this->generateView($tclass, $table));
        }

        $new_routes = $this->blade->view()->make('api.routes', [  'class' => $class, 'table' => $table ]);

        // add Dingo API methods into routes.php
        $translation_file = app_path('Http/routes.php');
        $current = file($translation_file);
        array_pop($current);
        array_pop($current);
        $current[] = "\n        ".$new_routes."\n    });\n});";
        file_put_contents($translation_file, $current);

        $this->info('Dingo API: '.$table.' created successfully.');
    }

    /**
     * Replace all stub tokens with properties.
     *
     * @param $view
     * @param $table
     *
     * @return mixed|string
     */
    protected function generateView($view, $table)
    {
        $class = Util::convertTableNameToClassName($table);
        $properties = $this->getTableProperties($table);

        $foreign_keys = $properties['foreign_keys'];
        foreach ($foreign_keys as $key => $val) {
            $properties['foreign_keys'][$key]->referenced_class_name = Util::convertTableNameToClassName($val->referenced_table_name);
        }

        $columns_json = [];
        foreach ($properties['columns'] as $key => $value) {
            $columns_json[$value['name']] = 'foo';
        }

        return $this->blade->view()->make('api.'.strtolower($view), [  'class' => $class,
                                            'table' => $table,
                                            'columns' => $properties['columns'],
                                            'columns_json' => json_encode($columns_json),
                                            'fillable' => $properties['fillable'],
                                            'guarded' => $properties['guarded'],
                                            'hidden' => $properties['hidden'],
                                            'foreign_keys' => $properties['foreign_keys'],
                                            'timestamps' => $properties['timestamps'],
                                            'softdeletes' => $properties['softdeletes']])->render();
    }

    /**
     * Fill up $fillable/$guarded/$timestamps properties based on table columns.
     *
     * @param $table
     *
     * @return array
     */
    protected function getTableProperties($table)
    {
        $fillable = [];
        $guarded = [];
        $hidden = [];
        $columns = [];
        $foreign_keys_columns = [];
        $timestamps = false;
        $softdeletes = false;

        $table_columns = $this->getTableColumns($table);
        $foreign_keys = $this->getForeignKeys($table);

        foreach ($foreign_keys AS $k => $v) {
            $foreign_keys_columns[] = $v->column_name;
        }

        foreach ($table_columns as $column) {

            //prioritize guarded properties and move to fillable
            if ($this->ruleProcessor->check($this->option('fillable'), $column->name)) {
                if(!in_array($column->name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $fillable[] = $column->name;
                }
            }
            if ($this->ruleProcessor->check($this->option('guarded'), $column->name)) {
                $guarded[] = $column->name;
            }

            //check if this model is timestampable
            if ($this->ruleProcessor->check($this->option('timestamps'), $column->name)) {
                $timestamps = true;
                $hidden[]  = $column->name;
            }

            //check if this model has deleted_at timestampable
            if ($this->ruleProcessor->check('equals:deleted_at', $column->name)) {
                $softdeletes = true;
            }

            if(in_array($column->name, $fillable) && !in_array($column->name, $foreign_keys_columns)) {
                $columns[] = ['name' => $column->name, 'type' => $column->type];
            }
        }

        return ['fillable' => $fillable,
                'guarded' => $guarded,
                'timestamps' => $timestamps,
                'hidden' => $hidden,
                'foreign_keys' => $foreign_keys,
                'softdeletes' => $softdeletes,
                'columns' => $columns];
    }

    /**
     * Get table columns.
     *
     * @param $table
     *
     * @return array
     */
    protected function getTableColumns($table)
    {
        $columns = \DB::select("SELECT COLUMN_NAME as `name`, DATA_TYPE as `type` FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'");

        return $columns;
    }

    /**
     * Get table columns.
     *
     * @param $table
     *
     * @return array
     */
    protected function getForeignKeys($table)
    {
        $columns = \DB::select("SELECT table_name, column_name, referenced_table_name, referenced_column_name  FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND referenced_table_name IS NOT NULL AND TABLE_NAME = '{$table}'");

        return $columns;
    }

    /**
     * Get stub file location.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__ . '/../stubs/model.stub';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['dir', null, InputOption::VALUE_OPTIONAL, 'SleeloingOwl Admin directory', app_path('Admin/')],
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'Rules for $fillable array columns', $this->fillableRules],
            ['guarded', null, InputOption::VALUE_OPTIONAL, 'Rules for $guarded array columns', $this->guardedRules],
            ['timestamps', null, InputOption::VALUE_OPTIONAL, 'Rules for $timestamps columns', $this->timestampRules],
            ['ignore', "i", InputOption::VALUE_OPTIONAL, 'Ignores the tables you define, separated with ,', null],
            ['ignoresystem', "s", InputOption::VALUE_NONE, 'If you want to ignore system tables.
            Just type --ignoresystem or -s'],
            ['getset', 'm', InputOption::VALUE_OPTIONAL, 'Defines if you want to generate set and get methods'],
            ['foreignkey', 'f', InputOption::VALUE_OPTIONAL, 'Defines if you want to generate relationships',$this->fkFunction],
        ];
    }
}
