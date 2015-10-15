# Model generator
Laravel 5 model generator for an existing schema. 

It plugs into your existing database and generates model class files based on the existing tables.

# Installation
Add ```"ernestovargas/laranerators": "1.0.*"``` to your composer.json file.

Add ```ErnestoVargas\Generator\ModelGeneratorProvider``` to your ```config/app.php``` providers array

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