<?php namespace ErnestoVargas\Generators\Commands;

use ErnestoVargas\Generators\Utilities\RuleProcessor;
use ErnestoVargas\Generators\Utilities\Util;
use ErnestoVargas\Generators\Utilities\BaseCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeDingoCommand extends BaseCommand
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
     * Default model namespace.
     *
     * @var string
     */
    protected $namespace = 'API/';


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
        $ignoreTable = $this->option("ignore");

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

        $class = Util::Table2ClassName($table);

        $this_classes = ['Controller', 'Request', 'Transformer'];
        foreach ($this_classes as $tclass) {
            $path = app_path('Api/' . $tclass . 's/' . $class . $tclass . '.php');

            if ($this->files->exists($path)) {
                return $this->error('Dingo API: ' . $table . $tclass . ' already exists!');
            }

            $this->makeDirectory($path);

            $this->files->put($path, "<?php \n\n" . $this->generateView($tclass, $table));
        }

        $new_routes = $this->blade->view()->make('api.routes', ['class' => $class, 'table' => $table]);

        // add Dingo API methods into routes.php
        $translation_file = app_path('Http/routes.php');
        $current = file($translation_file);
        array_pop($current);
        array_pop($current);
        $current[] = "\n        " . $new_routes . "\n    });\n});";
        file_put_contents($translation_file, $current);

        $this->info('Dingo API: ' . $table . ' created successfully.');
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['dir', null, InputOption::VALUE_OPTIONAL, 'SleeloingOwl Admin directory', $this->namespace],
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
