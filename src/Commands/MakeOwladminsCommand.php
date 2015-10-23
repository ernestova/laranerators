<?php namespace ErnestoVargas\Generators\Commands;

use ErnestoVargas\Generators\Utilities\RuleProcessor;
use ErnestoVargas\Generators\Utilities\Util;
use ErnestoVargas\Generators\Utilities\BaseCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeOwlAdminsCommand extends BaseCommand
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
     * Default model namespace.
     *
     * @var string
     */
    protected $namespace = 'Admin/';

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
    protected $guardedRules = "equals:id";

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
    protected $timestampRules = 'ends:_at';

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
            $this->process($table->name);
        }
    }

    /**
     * Generate a model file from a database table.
     *
     * @param $table
     */
    protected function process($table)
    {
        //prefix is the sub-directory within app
        $prefix = $this->option('dir');

        $ignoreTable = $this->option("ignore");
        $this->class = Util::Table2ClassName($table);
        $name = rtrim($this->parseName($prefix . $this->class), 's');
        $path = $this->getPath($name);

        if ($this->option("ignoresystem")) {
            $ignoreSystem = "users,permissions,permission_role,roles,role_user,users,migrations,password_resets";

            if (is_string($ignoreTable)) {
                $ignoreTable .= "," . $ignoreSystem;
            } else {
                $ignoreTable = $ignoreSystem;
            }
        }

        // if we have ignore tables, we need to find all the posibilites
        if (is_string($ignoreTable) && preg_match("/^" . $table . "|^" . $table . ",|," . $table . ",|," . $table . "$/", $ignoreTable)) {
            $this->info($table . " is ignored");
            return;
        }

        if ($this->files->exists($path)) {
            return $this->error('SleepingOwl Admin for ' . $table . ' already exists!');
        }

        $this->makeDirectory($path);

        $this->files->put($path, "<?php \n\n" . $this->generateView('admin', $table));

        // Include new Controller into menu.php
        $menu = "Admin::menu(\\App\\{$this->class}::class)->label(trans('admin.{$table}'))->icon('fa-bars');\n";
        file_put_contents(app_path('Admin/menu.php'), $menu, FILE_APPEND | LOCK_EX);

        // add string into translantions
        $translation_file = app_path('/../resources/lang/en/admin.php');
        $current = file_get_contents($translation_file);
        $current = str_ireplace('];', '', $current);
        $current .= "    '{$table}' => '" . ucwords(str_ireplace('_', ' ', $table)) . "',\n];";
        file_put_contents($translation_file, $current);

        $this->info('SleepingOwl Admin for ' . $table . ' created successfully.');
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
            ['dir', null, InputOption::VALUE_OPTIONAL, 'SleeoingOwl Admin directory', $this->namespace],
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'Rules for $fillable array columns', $this->fillableRules],
            ['guarded', null, InputOption::VALUE_OPTIONAL, 'Rules for $guarded array columns', $this->guardedRules],
            ['timestamps', null, InputOption::VALUE_OPTIONAL, 'Rules for $timestamps columns', $this->timestampRules],
            ['ignore', "i", InputOption::VALUE_OPTIONAL, 'Ignores the tables you define, separated with ,', null],
            ['ignoresystem', "s", InputOption::VALUE_NONE, 'If you want to ignore system tables.
            Just type --ignoresystem or -s'],
            ['getset', 'm', InputOption::VALUE_OPTIONAL, 'Defines if you want to generate set and get methods'],
            ['foreignkey', 'f', InputOption::VALUE_OPTIONAL, 'Defines if you want to generate relationships', $this->fkFunction],
        ];
    }
}
