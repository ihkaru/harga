<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalysisFeedbackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'analisis_harga_id' => 'required|exists:analisis_harga,id',
            'feedback_type' => 'required|in:positive,negative',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $feedback = AnalysisFeedback::create($validator->validated());

        return response()->json($feedback, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
