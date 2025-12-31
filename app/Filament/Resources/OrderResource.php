<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Operación';
    protected static ?string $modelLabel = 'Orden';
    protected static ?string $pluralModelLabel = 'Órdenes';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Encabezado')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('number')
                        ->label('No.')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Se genera automáticamente al guardar.'),

                    Forms\Components\DatePicker::make('received_date')
                        ->label('Fecha recepción')
                        ->required()
                        ->default(now()->toDateString()),

                    Forms\Components\TimePicker::make('received_time')
                        ->label('Hora recepción')
                        ->seconds(false)
                        ->default(now()->format('H:i')),

                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn() => Client::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->columnSpan(2),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->required()
                        ->default('recibido')
                        ->options([
                            'recibido' => 'Recibido',
                            'en_proceso' => 'En proceso',
                            'listo' => 'Listo',
                            'entregado' => 'Entregado',
                            'cancelado' => 'Cancelado',
                        ]),
                ]),

            Forms\Components\Section::make('Entrega')
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('delivery_date')
                        ->label('Fecha entrega')
                        ->default(fn() => Carbon::now()->addDay()->toDateString()),

                    Forms\Components\TimePicker::make('delivery_time')
                        ->label('Hora entrega')
                        ->seconds(false),
                ]),

            Forms\Components\Section::make('Detalle de servicios')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('Items')
                        ->relationship('items')
                        ->defaultItems(1)
                        ->reorderable(true)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['description'] ?? null)
                        ->schema([
                            Forms\Components\Select::make('service_id')
                                ->label('Servicio')
                                ->searchable()
                                ->preload()
                                ->options(fn() => Service::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if (!$state) {
                                        $set('unit_price', 0);
                                        $set('description', null);
                                        $set('subtotal', 0);
                                        return;
                                    }

                                    $service = Service::find($state);
                                    if (!$service)
                                        return;

                                    $set('description', $service->name);
                                    $set('unit_price', (float) $service->base_price);

                                    $qty = (float) ($get('quantity') ?: 1);
                                    $set('quantity', $qty);

                                    $subtotal = $qty * (float) $service->base_price;
                                    $set('subtotal', round($subtotal, 2));
                                })
                                ->required(),

                            Forms\Components\TextInput::make('description')
                                ->label('Descripción')
                                ->helperText('Puedes editar la descripción si aplica.')
                                ->maxLength(255)
                                ->reactive()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->default(1)
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(function (callable $set, callable $get) {
                                    $qty = (float) ($get('quantity') ?: 0);
                                    $price = (float) ($get('unit_price') ?: 0);
                                    $set('subtotal', round($qty * $price, 2));
                                })
                                ->required(),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Precio unitario (Q)')
                                ->numeric()
                                ->prefix('Q')
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(function (callable $set, callable $get) {
                                    $qty = (float) ($get('quantity') ?: 0);
                                    $price = (float) ($get('unit_price') ?: 0);
                                    $set('subtotal', round($qty * $price, 2));
                                })
                                ->required(),

                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal (Q)')
                                ->numeric()
                                ->prefix('Q')
                                ->disabled()
                                ->dehydrated(true)
                                ->required(),
                        ])
                        ->columns(4)
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, callable $get) {
                            self::recalcTotals($set, $get);
                        })
                        ->deleteAction(
                            fn(Forms\Components\Actions\Action $action) => $action->after(function (callable $set, callable $get) {
                                self::recalcTotals($set, $get);
                            })
                        )
                        ->addActionLabel('Agregar servicio'),
                ]),

            Forms\Components\Section::make('Totales')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('total')
                        ->label('Total (Q)')
                        ->numeric()
                        ->prefix('Q')
                        ->disabled()
                        ->dehydrated(true)
                        ->default(0),

                    Forms\Components\TextInput::make('paid')
                        ->label('Abono (Q)')
                        ->numeric()
                        ->prefix('Q')
                        ->default(0)
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, callable $get) {
                            self::recalcTotals($set, $get);
                        }),

                    Forms\Components\TextInput::make('balance')
                        ->label('Saldo (Q)')
                        ->numeric()
                        ->prefix('Q')
                        ->disabled()
                        ->dehydrated(true)
                        ->default(0),
                ]),

            Forms\Components\Section::make('Observaciones')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3)
                        ->maxLength(2000),
                ]),
        ]);
    }

    private static function recalcTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        $total = 0;

        foreach ($items as $item) {
            $total += (float) ($item['subtotal'] ?? 0);
        }

        $paid = (float) ($get('paid') ?: 0);
        $balance = max(0, $total - $paid);

        $set('total', round($total, 2));
        $set('balance', round($balance, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->label('Recepción')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'recibido' => 'Recibido',
                        'en_proceso' => 'En proceso',
                        'listo' => 'Listo',
                        'entregado' => 'Entregado',
                        'cancelado' => 'Cancelado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('GTQ')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('GTQ')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
