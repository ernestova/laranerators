namespace App\Api\Controllers;

use App\Models\{{ $class }};
use App\Http\Requests;
use App\Api\Requests\{{ $class }}Request;
use App\Api\Transformers\{{ $class }}Transformer;

/**
 * {{ $class }} resource representation.
 *
 * @Resource("{{ $class }}", uri="/{{ $table }}")
 */
class {{ $class }}Controller extends BaseController
{

    public function __construct()
    {
    }

    /**
     * Show all {{ $class }}
     *
     * Get a JSON representation of all the {{ $class }}
     *
     * @Get("/")
     * @Versions({"v1"})
     * @Response(200, body={"data": {{!! $columns_json !!}}})
     *
     */
    public function index()
    {
        return $this->collection({{ $class }}::all(), new {{ $class }}Transformer);
    }

    /**
     * Store a new {{ $class }} in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * @Post("/"    )
     * @Versions({"v1"})
     * @Request("name=foo&active=1", contentType="application/x-www-form-urlencoded")
     * @Response(200, body={"{{ $table }}": {!! $columns_json !!} })
     */
    public function store({{ $class }}Request $request)
    {
        return {{ $class }}::create($request->only(['name', 'active']));
    }

    /**
     * Display the specified {{ $class }} resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * @Get("/{id}")
     * @Versions({"v1"})
     * @Response(200, body={"data": {{!! $columns_json !!}}})
     */
    public function show($id)
    {
        return $this->item({{ $class }}::findOrFail($id), new {{ $class }}Transformer);
    }

    /**
     * Update the {{ $class }} in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update({{ $class }}Request $request, $id)
    {
        ${{ $class }} = {{ $class }}::findOrFail($id);
        ${{ $class }}->update($request->only(['name', 'age']));
        return ${{ $class }};
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * @Delete("/{id}")
     * @Versions({"v1"})
     * @Response(200, body=1)
     */
    public function destroy($id)
    {
        return {{ $class }}::destroy($id);
    }
}
