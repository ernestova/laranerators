
namespace App\Api\Transformers;

use App\Models\Manufacturers;
use League\Fractal\TransformerAbstract;

class {{ $class }}Transformer extends TransformerAbstract
{
	public function transform({{ $class }} $trnsfrm)
	{
		return [
			'id' 	=> (int) $trnsfrm->id,
@foreach ($columns as $key => $value)
	@if ($value['name'] == 'active')
		'active'	=> (boolean) $trnsfrm->active,
	@elseif ($value['type'] == 'int' or $value['type'] == 'decimal' or $value['type'] == 'double' or $value['type'] == 'float' or $value['type'] == 'bigint' )
		'{{ $value['name'] }}' => (float) $trnsfrm->{{ $value['name'] }},
	@elseif ($value['name'] == 'active' or $value['type'] == 'tinyint')
		'{{ $value['name'] }}' => (int) $trnsfrm->{{ $value['name'] }},
	@else
		'{{ $value['name'] }}' => $trnsfrm->{{ $value['name'] }},
	@endif
@endforeach
		];
	}
}
