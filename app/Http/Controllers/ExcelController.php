<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Traits\DatabaseTraitIndexing;
use Illuminate\Support\Facades\Response;
use ZipArchive;

class ExcelController extends Controller
{
    // use ExcelTrait;
    use DatabaseTraitIndexing;

    public function index()
    {
        return view('index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'excel' => 'required|mimes:xlsx,xls,csv|max:10240',
            'database_name' => 'required|string|max:255|regex:/^[a-zA-Z0-9_]+$/',
        ], [
            'excel.required' => 'Please select an Excel file to upload.',
            'excel.mimes' => 'The file must be an Excel file (.xlsx, .xls, or .csv).',
            'excel.max' => 'The file size must not exceed 10MB.',
            'database_name.required' => 'Database name is required.',
            'database_name.regex' => 'Database name can only contain letters, numbers, and underscores.',
            'database_name.max' => 'Database name must not exceed 255 characters.',
        ]);

        ini_set('max_execution_time', 0);  // Unlimited execution time
        ini_set('memory_limit', '-1');     // Unlimited memory
        set_time_limit(0);                 // Same as max_execution_time = 0

        $directory = 'excel';

        if (Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
        }

        if ($request->hasFile('excel')) {
            try {
                $file = $request->file('excel');
                $name = $file->getClientOriginalName();
                $excel = File::get($request->excel);
                $path = "/excel/imports/$name";
                $exist = Storage::disk('public')->exists($path);
                if ($exist) {
                    Storage::disk('public')->delete($path);
                }
                $storage = Storage::disk('public')->put($path, $excel);

                if (!$storage) {
                    return redirect()->back()->with('error', 'Failed to store the file. Please try again.');
                }

                $this->getCollection($path, $request->database_name);

                return redirect()->back()->with('success', 'Excel file data has been transferred to the database successfully!');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Error processing file: ' . $e->getMessage());
            }
        } else {
            return redirect()->back()->with('error', 'File not found. Please select a file to upload.');
        }
    }

    public function download()
    {
        $path = public_path();
        $files = File::glob($path . '/storage/excel/*.xlsx');
        $zip = new ZipArchive;
        $zipfilename = 'excels.zip';
        if ($zip->open(public_path($zipfilename), ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            return Response::download(public_path($zipfilename))->deleteFileAfterSend(true);
        }
        return response()->json('Something went wrong.');
    }

    public function removeFile()
    {
        $path = public_path();
        $files = File::glob($path . '/storage/excel/*.xlsx');
        if ($files > 0) {
            foreach ($files as $file) {
                File::delete($file);
            }
        }
        return response()->json('Excel files are deleted successfully.');
    }
}
