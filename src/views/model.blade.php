
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
@if ($softdeletes)
use Illuminate\Database\Eloquent\SoftDeletes;
@endif
@if ($activitylog)
use Spatie\Activitylog\LogsActivityInterface;
use Spatie\Activitylog\LogsActivity;
@endif

class {{ $class }} extends Model @if ($activitylog)implements LogsActivityInterface @endif
{

@if ($uses)
    use {{ $uses }};
@endif
@if ($softdeletes)
    public $timestamps = TRUE;

@endif
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = '{{ $table }}';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = {!! $fillable !!};

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = {!! $hidden !!};

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = {!! $hidden !!};

@foreach ($foreign_keys as $fk)
    /**
     * A {{ $fk->referenced_table_name }} can have one {{ $fk->referenced_class_name }}
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function {{ $fk->referenced_table_name }}()
    {
        return $this->hasOne('\App\Models\{{ $fk->referenced_class_name }}','{{ $fk->referenced_column_name }}');
    }

@endforeach
@if ($activitylog)
    /**
    * Get the message that needs to be logged for the given event name.
    *
    * @param string $eventName
    * @return string
    */
    public function getActivityDescriptionForEvent($eventName)
    {
        if ($eventName == 'created')
        {
        return 'Act:+ Ent:{{ $class }} Val:"' . $this->name . '"';
        }

        if ($eventName == 'updated')
        {
        return 'Act:* Ent:{{ $class }} Val:"' . $this->name . '"';
        }

        if ($eventName == 'deleted')
        {
        return 'Act:- Ent:{{ $class }} Val:"' . $this->name . '"';
        }

        return '';
    }

@endif

}