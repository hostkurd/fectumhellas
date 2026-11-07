<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Toggle;

/** Blue band: heading and points beside a photo ("Why work with us"). */
final class FeatureBand extends Block
{
    protected static string $type = 'feature_band';
    protected static string $category = 'site';
    protected static string $icon = 'star';
    protected static ?string $label = 'fh.block.feature_band';
    protected static ?string $description = 'fh.block.feature_band_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
            Repeater::make('points')->label('fh.bf.points')->schema([
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:300'),
            ])->itemLabel('title')->maxItems(4)->columns(2)->columnSpan('full'),
        ];
    }
}
