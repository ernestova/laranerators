
namespace App\Api\Requests;

use App\Http\Requests\Request;

class {{ $class }}Request extends Request
{
	public function authorize()
	{
		return true;
	}

	public function rules()
	{
		return [
@foreach ($columns as $key => $value)
		'{{ $value['name'] }}' => '',
@endforeach
    	];
	}
}
