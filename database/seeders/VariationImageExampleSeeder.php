<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\CollectionCategory;
use App\Models\CollectionImage;
use App\Models\CollectionVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * One worked example: a product whose every variation has its own photograph.
 *
 * "Do variation photos work?" is easier to answer by looking at one than by
 * reading about it. This builds a single demonstration product with four
 * variations — three designs and a larger size that costs more — and gives each
 * one a different picture, so the till, the invoice picker and the inventory
 * list can all be seen showing the right photograph against the right variation.
 *
 * The pictures are drawn here with GD rather than shipped as files: nothing to
 * commit, nothing to lose on a deploy, and each one is unmistakably distinct so
 * a photo appearing against the wrong variation is obvious at a glance.
 *
 * Safe to run repeatedly — it rebuilds its own product and touches nothing else.
 */
class VariationImageExampleSeeder extends Seeder
{
    /** Identifies the demo product, so re-running replaces rather than duplicates. */
    protected const SKU = 'DEMO-VAR-PHOTOS';

    protected const NAME = 'Sample Topi — Variation Photos (demo)';

    /**
     * Each variation, with the colours its picture is drawn in.
     *
     * @var array<int, array{size: string, color: string, design: ?string, price: ?float, stock: int, ink: array{int,int,int}, wash: array{int,int,int}}>
     */
    protected array $variations = [
        ['size' => '21.5', 'color' => 'White',  'design' => 'Design 1', 'price' => null, 'stock' => 6,
         'ink' => [51, 51, 51],    'wash' => [246, 244, 239]],
        ['size' => '21.5', 'color' => 'Navy',   'design' => 'Design 2', 'price' => null, 'stock' => 4,
         'ink' => [255, 255, 255], 'wash' => [28, 45, 78]],
        ['size' => '21.5', 'color' => 'Maroon', 'design' => 'Design 3', 'price' => null, 'stock' => 3,
         'ink' => [255, 255, 255], 'wash' => [104, 29, 42]],
        ['size' => '22.5', 'color' => 'Gold',   'design' => 'Design 1', 'price' => 2400.00, 'stock' => 2,
         'ink' => [61, 44, 10],    'wash' => [214, 178, 96]],
    ];

    public function run(): void
    {
        if (! extension_loaded('gd')) {
            $this->command?->warn('GD is not available — skipping the variation photo example.');

            return;
        }

        $collection = $this->product();

        $this->clearPrevious($collection);

        // A lead photograph for the product itself, so the fallback is visibly
        // different from any of the variations' own pictures.
        $this->attachImage(
            $collection,
            null,
            $this->draw('Sample Topi', 'all designs', [90, 84, 74], [232, 228, 220]),
            $collection->name,
            true,
            0
        );

        $sort = 0;

        foreach ($this->variations as $i => $spec) {
            $variant = CollectionVariant::create([
                'collection_id' => $collection->id,
                'size' => $spec['size'],
                'color' => $spec['color'],
                'design' => $spec['design'],
                'sku' => self::SKU . '-' . ($i + 1),
                'price' => $spec['price'],
                'stock_qty' => $spec['stock'],
                'reserved_qty' => 0,
                'status' => 'active',
                'sort_order' => $i + 1,
            ]);

            $this->attachImage(
                $collection,
                $variant,
                $this->draw($spec['color'], $spec['design'] . ' · size ' . $spec['size'], $spec['ink'], $spec['wash']),
                $collection->name . ' — ' . $variant->label,
                false,
                ++$sort
            );

            // Puts this variation's picture on the column every listing reads.
            $variant->syncImagePathFromGallery();
        }

        $collection->syncImagePathFromGallery();
        $collection->recalcStockFromVariants();

        $this->command?->info(sprintf(
            'Variation photo example ready: "%s" — %d variations, each with its own picture.',
            $collection->name,
            count($this->variations)
        ));
    }

    /**
     * The demo product itself, created once and reused on later runs so its id
     * (and any invoice that referenced it) stays stable.
     */
    protected function product(): Collection
    {
        $category = CollectionCategory::query()->orderBy('id')->first();

        $collection = Collection::where('sku', self::SKU)->first();

        $attributes = [
            'name' => self::NAME,
            'sku' => self::SKU,
            'category_id' => $category?->id,
            'description' => 'A demonstration of one product sold as several variations, each with its own photograph, price and stock. Safe to delete.',
            'price' => 1800.00,
            'cost_price' => 0,
            'status' => 'active',
            'show_on_shop' => false,
        ];

        if ($collection) {
            $collection->update($attributes);

            return $collection->fresh();
        }

        return Collection::create($attributes + ['stock_qty' => 0]);
    }

    /**
     * Remove what a previous run left behind — the image files too, so repeated
     * seeding does not quietly fill the disk.
     */
    protected function clearPrevious(Collection $collection): void
    {
        foreach ($collection->images()->get() as $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete();
        }

        $collection->variants()->delete();
    }

    protected function attachImage(
        Collection $collection,
        ?CollectionVariant $variant,
        string $path,
        string $alt,
        bool $isPrimary,
        int $sortOrder
    ): CollectionImage {
        return CollectionImage::create([
            'collection_id' => $collection->id,
            'collection_variant_id' => $variant?->id,
            'path' => $path,
            'alt' => $alt,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Draw one square picture and store it on the public disk.
     *
     * Deliberately plain — a wash of colour, the shop's initials, and the words
     * naming the variation — because the point is to tell four pictures apart at
     * thumbnail size, not to look like a photograph.
     *
     * @param  array{int,int,int}  $ink
     * @param  array{int,int,int}  $wash
     * @return string the stored path, relative to the public disk
     */
    protected function draw(string $heading, string $subheading, array $ink, array $wash): string
    {
        $size = 600;
        $canvas = imagecreatetruecolor($size, $size);

        $background = imagecolorallocate($canvas, ...$wash);
        $foreground = imagecolorallocate($canvas, ...$ink);

        imagefilledrectangle($canvas, 0, 0, $size, $size, $background);

        // A ring, so the picture reads as a cap rather than a plain swatch.
        // Drawn as two filled ellipses: imageellipse ignores line thickness on
        // some GD builds and comes out hairline.
        $cx = (int) ($size / 2);
        $cy = (int) ($size * 0.40);
        imagefilledellipse($canvas, $cx, $cy, (int) ($size * 0.56), (int) ($size * 0.56), $foreground);
        imagefilledellipse($canvas, $cx, $cy, (int) ($size * 0.40), (int) ($size * 0.40), $background);
        imagefilledellipse($canvas, $cx, $cy, (int) ($size * 0.14), (int) ($size * 0.14), $foreground);

        // GD's built-in bitmap font is the only one guaranteed on this host, and
        // it is ASCII-only and tiny — so the words are drawn small and scaled up.
        $this->bigText($canvas, self::ascii($heading), (int) ($size * 0.70), $size, $ink, $wash, 4);
        $this->bigText($canvas, self::ascii($subheading), (int) ($size * 0.81), $size, $ink, $wash, 2);
        $this->bigText($canvas, 'LIBAS UL ANWAR', (int) ($size * 0.90), $size, $ink, $wash, 1);

        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', self::ascii($heading . '-' . $subheading)), '-'));
        $path = 'collections/demo-variation-' . $slug . '.jpg';

        ob_start();
        imagejpeg($canvas, null, 86);
        $bytes = (string) ob_get_clean();

        imagedestroy($canvas);

        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Draw a line of text centred and enlarged.
     *
     * The built-in font tops out around 9x15 pixels, which is unreadable on a
     * 600px card. Rendering onto a small strip and resampling it up gives large,
     * legible letters without depending on a TrueType font being installed.
     */
    protected function bigText($canvas, string $text, int $y, int $width, array $ink, array $wash, int $scale): void
    {
        if ($text === '') {
            return;
        }

        $font = 5;
        $stripWidth = max(1, imagefontwidth($font) * strlen($text) + 2);
        $stripHeight = imagefontheight($font) + 2;

        $strip = imagecreatetruecolor($stripWidth, $stripHeight);
        imagefilledrectangle($strip, 0, 0, $stripWidth, $stripHeight, imagecolorallocate($strip, ...$wash));
        imagestring($strip, $font, 1, 1, $text, imagecolorallocate($strip, ...$ink));

        $targetWidth = $stripWidth * $scale;
        $targetHeight = $stripHeight * $scale;

        // Never let a long line run off the card.
        if ($targetWidth > $width - 40) {
            $targetHeight = (int) round($targetHeight * ($width - 40) / $targetWidth);
            $targetWidth = $width - 40;
        }

        imagecopyresampled(
            $canvas, $strip,
            (int) (($width - $targetWidth) / 2), $y,
            0, 0,
            $targetWidth, $targetHeight,
            $stripWidth, $stripHeight
        );

        imagedestroy($strip);
    }

    /**
     * The built-in font speaks ASCII only; anything else prints as rubbish.
     */
    protected static function ascii(string $text): string
    {
        $text = str_replace(['·', '—', '–', '’'], ['-', '-', '-', "'"], $text);

        return trim(preg_replace('/[^\x20-\x7E]/', '', $text));
    }
}
