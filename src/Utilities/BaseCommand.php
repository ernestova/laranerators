<?php namespace ErnestoVargas\Generators\Utilities;

use Illuminate\Console\GeneratorCommand;
use Philo\Blade\Blade;

abstract class BaseCommand extends GeneratorCommand
{
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
     * Get stub file location.
     *
     * @return string
     */
    public function getStub()
    {
        return __DIR__ . '/../stubs/model.stub';
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
                if (!in_array($column->name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                    $fillable[] = $column->name;
                }
            }
            if ($this->ruleProcessor->check($this->option('guarded'), $column->name)) {
                $guarded[] = $column->name;
            }

            //check if this model is timestampable
            if ($this->ruleProcessor->check($this->option('timestamps'), $column->name)) {
                $timestamps = true;
                $hidden[] = $column->name;
            }

            //check if this model has deleted_at timestampable
            if ($this->ruleProcessor->check('equals:deleted_at', $column->name)) {
                $softdeletes = true;
            }

            if (in_array($column->name, $fillable) && !in_array($column->name, $foreign_keys_columns)) {
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
     * Replace all stub tokens with properties.
     *
     * @param $name
     * @param $table
     *
     * @return mixed|string
     */
    protected function generateView($name, $table)
    {
        $class = Util::Table2ClassName($table);
        $properties = $this->getTableProperties($table);

        $activitylog = TRUE;
        $uses = [];

        $foreign_keys = $properties['foreign_keys'];
        foreach ($foreign_keys as $key => $val) {
            $properties['foreign_keys'][$key]->referenced_class_name = Util::Table2ClassName($val->referenced_table_name);
        }

        $columns_json = [];
        foreach ($properties['columns'] as $key => $value) {
            $columns_json[$value['name']] = 'foo';
        }

        if ($properties['softdeletes']) $uses[] = 'SoftDeletes';
        if ($activitylog) $uses[] = 'LogsActivity';

        $blade = new Blade($this->views, $this->cache);
        return $blade->view()->make('admin', ['activitylog' => $activitylog,
            'class' => $class,
            'columns' => $properties['columns'],
            'columns_json' => json_encode($columns_json),
            'fillable' => $properties['fillable'],
            'guarded' => $properties['guarded'],
            'hidden' => $properties['hidden'],
            'foreign_keys' => $properties['foreign_keys'],
            'table' => $table,
            'timestamps' => $properties['timestamps'],
            'softdeletes' => $properties['softdeletes']])->render();

    }
}
