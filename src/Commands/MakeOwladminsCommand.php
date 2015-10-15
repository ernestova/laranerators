<?php namespace ErnestoVargas\Laranerators\Commands;

use ErnestoVargas\Laranerators\Utilities\RuleProcessor;
use ErnestoVargas\Laranerators\Utilities\VariableConversion;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Console\GeneratorCommand;
use Philo\Blade\Blade;

class MakeOwladminsCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:owladmins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build SleepingOwl Admin model configurations.';

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
        //prefix is the sub-directory within app
        $prefix = $this->option('dir');

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

        $class = VariableConversion::convertTableNameToClassName($table);

        $name = rtrim($this->parseName($prefix . $class), 's');
        $path = app_path('Admin/'.$class.'.php');

        if ($this->files->exists($path)) {
            return $this->error('SleepingOwl Admin for '.$table.' already exists!');
        }

        $this->makeDirectory($path);

        $this->files->put($path, "<?php \n\n".$this->generateView($name, $table));

        // Include Admin Controller into menu.php
        $menu = "Admin::menu(\\App\\{$class}::class)->label(trans('admin.{$table}'))->icon('fa-bars');\n";
        file_put_contents(app_path('Admin/menu.php'), $menu, FILE_APPEND | LOCK_EX);

        // add string into translantions
        $translation_file = app_path('/../resources/lang/en/admin.php');
        $current = file_get_contents($translation_file);
        $current = str_ireplace('];','',$current);
        $current .= "    '{$table}' => '". ucwords(str_ireplace('_',' ',$table)) ."',\n];";
        file_put_contents($translation_file, $current);
            
        $this->info('SleepingOwl Admin for '.$table.' created successfully.');
    }

    /**
     * Replace all stub tokens with properties.
     *
     * @param $name
     * @param $table
     *
     * @return mixed|string
     */
    protected function generateView($name, $table)
    {
        $class = VariableConversion::convertTableNameToClassName($table);
        $properties = $this->getTableProperties($table);

        $foreign_keys = $properties['foreign_keys'];
        foreach ($foreign_keys as $key => $val) {
            $properties['foreign_keys'][$key]->referenced_class_name = VariableConversion::convertTableNameToClassName($val->referenced_table_name);
        }

        $blade = new Blade($this->views, $this->cache);
        return $blade->view()->make('admin', [  'class' => $class,
                                                'table' => $table,
                                                'columns' => $properties['columns'],
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
