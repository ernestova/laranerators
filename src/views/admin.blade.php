Admin::model('App\Models\{{ $class }}')->title(trans('admin.{{ $table }}'))->display(function ()
{
	$display = AdminDisplay::table();
	$display->actions([
		Column::action('pdf')->value('PDF')->icon('fa-file-pdf-o')->target('_blank')->callback(function ($collection)
		{
			$filename = trans('admin.{{ $table }}').'_'.Uuid::generate();
			$data = $collection->toArray();
			Excel::create($filename, function($excel) use($data) {
				$excel->sheet(trans('admin.{{ $table }}'), function($sheet) use($data) {
					$sheet->fromArray($data);
				});
			})->export('pdf');
		}),
		Column::action('xls')->value('XLS')->icon('fa-file-excel-o')->target('_blank')->callback(function ($collection)
		{
			$filename = trans('admin.{{ $table }}').'_'.Uuid::generate(1);
			$data = $collection->toArray();
			Excel::create($filename, function($excel) use($data) {
				$excel->sheet(trans('admin.{{ $table }}'), function($sheet) use($data) {
					$sheet->fromArray($data);
				});
			})->export('xls');
		})
	]);
	$display->columns([
	 Column::checkbox(),
@foreach ($columns as $key => $value)
 @if ($value['name'] == 'active')
	 Column::custom()->label(trans('admin.active'))->callback(function ($instance)
	 {
	 	return $instance->active ? '&check;' : '-';
	 }),
 @elseif ($value['type'] == 'date' or $value['type'] == 'datetime')
	 Column::datetime('{{ $value['name'] }}')->label(trans('admin.{{ $value['name'] }}'))->format(trans('admin.fmt_date')),
 @elseif ($value['type'] == 'int')
 @else
	 Column::string('{{ $value['name'] }}')->label(trans('admin.{{ $value['name'] }}')),
 @endif
@endforeach
	]);
	return $display;
})->createAndEdit(function ()
{
	$form = AdminForm::form();
	$form->items([
		FormItem::columns()->columns([
			[
@foreach ($foreign_keys as $fk)
	FormItem::select('{{ $fk->column_name }}', trans('admin.{{ $fk->referenced_table_name }}'))->model('App\Models\{{ $fk->referenced_class_name }}')->display('name')->required(),
@endforeach

@foreach ($columns as $key => $value)
@if ($value['name'] == 'active' or $value['type'] == 'tinyint')
		FormItem::checkbox('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@elseif ($value['type'] == 'char' or $value['type'] == 'varchar' )
		FormItem::text('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@elseif ($value['type'] == 'int' or $value['type'] == 'decimal' or $value['type'] == 'double' or $value['type'] == 'float' or $value['type'] == 'bigint' )
		FormItem::textaddon('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->addon('#')->placement('before')->required(),
@elseif ($value['type'] == 'date' or $value['type'] == 'datetime' or $value['type'] == 'time' or $value['type'] == 'timestamp')
@else
		FormItem::text('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@endif
@endforeach
		],[
@foreach ($columns as $key => $value)
@if($value['type'] == 'text' )
	FormItem::textarea('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@elseif ($value['type'] == 'date' or $value['type'] == 'datetime')
	FormItem::date('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@elseif ($value['type'] == 'time')
	FormItem::time('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@elseif ($value['type'] == 'timestamp')
	FormItem::timestamp('{{ $value['name'] }}', trans('admin.{{ $value['name'] }}'))->required(),
@endif
@endforeach
			],
		]),
	]);
	return $form;
});
