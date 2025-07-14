<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;

class RoleController extends Controller
{

    public function update(Request $request, $id)
    {
        try {

            $validated = $request->validate([
                'name' => 'nullable|string',
                'permissions' => 'array',
                'permissions.*' => 'string',
            ]);

            $user = User::findOrFail($id);

            if(!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validated['permissions'] = $validated['permissions'] ?? [];

            $permission = Profile::updateOrInsert([
                ['user_id' => $id],
                [$validated]
            ]);

            return response()->json([
                'success' => true,
                'message' => "Permission mis à jour avec succès"
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }

    }


}
