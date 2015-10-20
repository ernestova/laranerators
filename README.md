
[![Build Status](https://travis-ci.org/ernestova/laranerators.svg)] (https://travis-ci.org/ernestova/laranerators.svg) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/1fc32eff-58ae-492a-84fb-e47be21f11d6/mini.png)](https://insight.sensiolabs.com/projects/1fc32eff-58ae-492a-84fb-e47be21f11d6)


# Model generator
Laravel 5 model generator for an existing MySql schema.

It reads your existing database schema and generates model class files based on the existing tables.

# Installation
Add ```"ernestovargas/laranerators": "dev-master"``` to your require-dev section on your composer.json file.

Because the generators are only useful for development, add the provider in app/Providers/AppServiceProvider.php, like:
```php
public function register()
{
    if ($this->app->environment() == 'local') {
        $this->app->register('ErnestoVargas\Generators\GeneratorsProvider');
    }
}
```

# Help & Options
```php artisan help make:models```

Options:
 - --dir=""                 Model directory (default: "Models/")
 - --extends=""             Parent class (default: "Model")
 - --fillable=""            Rules for $fillable array columns (default: "")
 - --guarded=""             Rules for $guarded array columns (default: "ends:_id|ids,equals:id")
 - --timestamps=""          Rules for $timestamps columns (default: "ends:_at")
 - --ignore=""|-i=""        A table names to ignore
 - --ignoresystem|-s        List of system tables (auth, migrations, entrust package)
