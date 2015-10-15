        $api->get('{{ $table }}', '{{ $class }}Controller@index');
        $api->post('{{ $table }}', '{{ $class }}Controller@store');
        $api->get('{{ $table }}/{id}', '{{ $class }}Controller@show');
        $api->delete('{{ $table }}/{id}', '{{ $class }}Controller@destroy');
        $api->put('{{ $table }}/{id}', '{{ $class }}Controller@update');