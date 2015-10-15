<?php namespace ErnestoVargas\Laranerators\Commands;

use ErnestoVargas\Laranerators\Utilities\RuleProcessor;
use ErnestoVargas\Laranerators\Utilities\SetGetGenerator;
use ErnestoVargas\Laranerators\Utilities\VariableConversion;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Console\GeneratorCommand;
use Philo\Blade\Blade;

class MakeModelsCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build models from existing schema.';

    /**
     * Default model namespace.
     *
     * @var string
     */
    protected $namespace = 'Models/';

    /**
     * Default class the model extends.
     *
     * @var string
     */
    protected $extends = 'Model';

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
     * Rules for columns that set whether the timestamps property is set to true/false.
     *
     * @var array
     */
    protected $timestampRules = 'ends:_at'; //['ends' => ['_at']];

    /**
     * Contains the template stub for set function
     * @var string
     */
    protected $setFunctionStub;
    /**
     * Contains the template stub for get function
     * @var string
     */
    protected $getFunctionStub;

    /**
     * Contains the template stub for get function
     * @var string
     */
    protected $fkFunction;


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
            $ignoreSystem = "administrators,users,permissions,permission_role,roles,role_user,users,migrations,password_resets";

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

        if ($this->files->exists($path = $this->getPath($name))) {
            return $this->error($this->extends . ' for '.$table.' already exists!');
        }

        $this->makeDirectory($path);

        $this->files->put($path, "<?php \n\n".$this->replaceTokens($name, $table));

        $this->info($this->extends . ' for '.$table.' created successfully.');
    }

    /**
     * Replace all stub tokens with properties.
     *
     * @param $name
     * @param $table
     *
     * @return mixed|string
     */
    protected function replaceTokens($name, $table)
    {
        $class = VariableConversion::convertTableNameToClassName($table);
        $properties = $this->getTableProperties($table);

        $views = __DIR__ . '/../views';
        $cache = '/tmp';

        $activitylog = TRUE;
        $uses = [];

        $foreign_keys = $properties['foreign_keys'];
        foreach ($foreign_keys as $key => $val) {
            $properties['foreign_keys'][$key]->referenced_class_name = VariableConversion::convertTableNameToClassName($val->referenced_table_name);
        }

        if ($properties['softdeletes']) $uses[] = 'SoftDeletes';
        if ($activitylog) $uses[] = 'LogsActivity';

        $blade = new Blade($views, $cache);
        return $blade->view()->make('model', ['class' => $class,
                                            'table' => $table,
                                            'activitylog' => $activitylog, // Convert to parameter
                                            'fillable' => VariableConversion::convertArrayToString($properties['fillable']),
                                            'guarded' => VariableConversion::convertArrayToString($properties['guarded']),
                                            'hidden' => VariableConversion::convertArrayToString($properties['hidden']),
                                            'foreign_keys' => $properties['foreign_keys'],
                                            'timestamps' => $properties['timestamps'],
                                            'uses' => implode(", ", $uses),
                                            'softdeletes' => $properties['softdeletes']])->render();

    }

    /**
     * Replaces setters and getters from the stub. The functions are created
     * from provider properties.
     * 
     * @param  array $properties 
     * @param  string $class      
     * @return string
     */
    protected  function replaceTokensWithSetGetFunctions($properties, $class) {
        $getters = "";
        $setters = "";

        $fillableGetSet = new SetGetGenerator($properties['fillable'], $this->getFunctionStub, $this->setFunctionStub);
        $getters .= $fillableGetSet->generateGetFunctions();
        $setters .= $fillableGetSet->generateSetFunctions();

        $guardedGetSet = new SetGetGenerator($properties['guarded'], $this->getFunctionStub, $this->setFunctionStub);
        $getters .= $guardedGetSet->generateGetFunctions();

        return str_replace([
            '{{setters}}',
            '{{getters}}'
            ], [
            $setters,
            $getters
            ], $class);
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
        $timestamps = false;
        $softdeletes = false;

        $columns = $this->getTableColumns($table);
        $foreign_keys = $this->getForeignKeys($table);

        foreach ($columns as $column) {

            //priotitze guarded properties and move to fillable
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
        }

        return ['fillable' => $fillable, 'guarded' => $guarded, 'timestamps' => $timestamps, 'hidden' => $hidden, 'foreign_keys' => $foreign_keys, 'softdeletes' => $softdeletes];
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
        $columns = \DB::select("SELECT COLUMN_NAME as `name` FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'");

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
            ['dir', null, InputOption::VALUE_OPTIONAL, 'Model directory', $this->namespace],
            ['extends', null, InputOption::VALUE_OPTIONAL, 'Parent class', $this->extends],
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
