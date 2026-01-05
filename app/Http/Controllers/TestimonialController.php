<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class TestimonialController extends Controller
{
    public function getImages()
    {
        $path = public_path('img/testimonial');
        
        if (!File::isDirectory($path)) {
            return [];
        }
        
        $images = File::files($path);
        $testimonials = [];
        
        foreach ($images as $image) {
            $filename = $image->getFilename();
            $testimonials[] = [
                'image' => 'img/testimonial/' . $filename,
                'filename' => $filename,
            ];
        }
        
        // Ordena alfabeticamente
        sort($testimonials);
        
        return $testimonials;
    }
}
