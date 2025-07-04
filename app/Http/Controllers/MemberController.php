<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Site;
use App\Models\Pool;
use App\Models\Fonction;
use App\Models\Role;
use App\Models\User;
use App\Models\City;
use App\Models\Township;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function index()
    {
        try {
            $members = Member::orderBy('created_at', 'desc')->paginate(10);
            return response()->json([
                'success' => true,
                'members' => $members
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        try {
            $sites = Site::active()->get();
            $pools = Pool::active()->get();
            $fonctions = Fonction::all();
            $cities = City::with('country')->get();
            $townships = Township::with('city')->get();
            // return view('members.create', compact('sites', 'pools', 'fonctions', 'cities', 'townships'));

            return view('members.create', [
                'success' => true,
                'sites' => $sites,
                'pools' => $pools,
                'fonctions' => $fonctions,
                'cities' => $cities,
                'townships' => $townships
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'firstname' => 'nullable|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'site_id' => 'required|exists:sites,id',
                'city_id' => 'nullable|exists:cities,id',
                'township_id' => 'nullable|exists:townships,id',
                'pool_id' => 'nullable|exists:pools,id',
                'chef_id' => 'nullable|exists:members,id',
                'category' => 'nullable|string|max:2',
                'street' => 'nullable|string|max:255',
                'libelle_pool' => 'nullable|string|max:255',
                'fonction_id' => 'nullable|exists:fonctions,id',
                'face_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'face_base64' => 'nullable|string',
                'date_adhesion' => 'nullable|date',
                'is_active' => 'nullable|boolean'
            ]);
            // dd($validated);
            $data = $request->all();

            // Générer automatiquement le numéro de membre
            $data['membershipNumber'] = $this->generateMembershipNumber($request->site_id, $request->city_id);

            // Gérer l'upload d'image
            $data['face_path'] = $this->handleImageUpload($request);

            $member = Member::create($data);

            return response()->json([
                'success' => true,
                'member' => $member->load('site', 'city', 'township', 'pool', 'fonction'),
                'message' => 'Membre créé avec succès'
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $member = Member::with('site', 'city', 'township', 'pool', 'fonction', 'chef', 'user')->findOrFail($id);

            $base64Image = null;

            if ($member->face_path && Storage::disk('public')->exists($member->face_path)) {
                $fileContent = Storage::disk('public')->get($member->face_path);
                $mimeType = Storage::disk('public')->mimeType($member->face_path);
                $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }

            return response()->json([
                'success' => true,
                'member' => $member,
                'face_base64' => null,
                // 'face_base64' => $base64Image,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " . $th->getMessage()
            ], 500);
        }
    }


    public function edit($id)
    {

        try {

            $sites = Site::active()->get();
            $pools = Pool::active()->get();
            $fonctions = Fonction::all();
            $cities = City::with('country')->get();
            $townships = Township::with('city')->get();
            $member = Member::findOrFail($id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            return response()->json([
                'member' => $member,
                'success' => true,
                'sites' => $sites,
                'pools' => $pools,
                'fonctions' => $fonctions,
                'cities' => $cities,
                'townships' => $townships
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {

        try {
            $request->validate([
                'firstname' => 'nullable|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'site_id' => 'required|exists:sites,id',
                'city_id' => 'nullable|exists:cities,id',
                'township_id' => 'nullable|exists:townships,id',
                'pool_id' => 'nullable|exists:pools,id',
                'chef_id' => 'nullable|exists:members,id',
                'category' => 'required|string|max:2',
                'street' => 'nullable|string|max:255',
                'libelle_pool' => 'nullable|string|max:255',
                'fonction_id' => 'nullable|exists:fonctions,id',
                'face_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'face_base64' => 'nullable|string',
                'date_adhesion' => 'nullable|date',
                'is_active' => 'nullable|boolean'
            ]);

            $member = Member::findOrFail($request->member_id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            $data = $request->all();

            // Régénérer le numéro de membre si le site ou la ville a changé
            if ($request->site_id != $member->site_id || $request->city_id != $member->city_id) {
                $data['membershipNumber'] = $this->generateMembershipNumber($request->site_id, $request->city_id);
            }

            // Gérer l'upload d'image
            $newImagePath = $this->handleImageUpload($request);
            if ($newImagePath) {
                // Supprimer l'ancienne image si elle existe
                if ($member->face_path && Storage::disk('public')->exists($member->face_path)) {
                    Storage::disk('public')->delete($member->face_path);
                }
                $data['face_path'] = $newImagePath;
            }

            $member->update($data);

            return response()->json([
                'member' => $member,
                'success' => true,
                'message' => 'Membre mis à jour avec succès'
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function destroy(Member $id)
    {
        try {

            $member = Member::findOrFail($id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            // Supprimer l'image si elle existe
            if ($member->face_path && Storage::disk('public')->exists($member->face_path)) {
                Storage::disk('public')->delete($member->face_path);
            }

            $member->delete();

            return response()->json([
                'member' => $member,
                'success' => true,
                'message' => 'Membre supprimé avec succès',
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function assignRole(Request $request)
    {
        try {
            $request->validate([
                'role_id' => 'required|exists:roles,id',
                'member_id' => 'required|exists:members,id'
            ]);

            $member = Member::findOrFail($request->member_id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            $role = Role::find($request->role_id);

            if ($member->user) {
                $member->user->update(['role_id' => $role->id]);
            }

            return response()->json([
                'member' => $member,
                'success' => true,
                'message' => 'Rôle assigné avec succès !',
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function createUser(Request $request)
    {

        try {
            $request->validate([
                'email' => 'required|email|unique:users,email',
                'username' => 'required|string|unique:users,username',
                'password' => 'required|string|min:8',
                'role_id' => 'required|exists:roles,id',
                'member_id' => 'required|exists:members,id'
            ]);

            $member = Member::findOrFail($request->member_id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }


            if ($member->hasUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce membre a déjà un compte utilisateur'
                ], 404);
            }

            $user = User::create([
                'member_id' => $member->id,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'is_active' => true
            ]);

            return response()->json([
                'user' => $user,
                'success' => true,
                'message' => 'Compte utilisateur créé avec succès',
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function showAssignRole($id)
    {

        try {
            $member = Member::findOrFail($id);
            $roles = Role::active()->whereIn('name', ['coordonateur', 'chef_de_pool'])->get();

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            return response()->json([
                'member' => $member,
                'roles' => $roles,
                'success' => true,
                // 'message' => 'Rôle assigné avec succès !',
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    public function showCreateUser($id)
    {

        try {

            $member = Member::findOrFail($id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            if ($member->hasUser()) {
                // return redirect()->back()
                //     ->with('error', 'Ce membre a déjà un compte utilisateur.');
                return response()->json([
                    'success' => false,
                    'message' => 'Ce membre a déjà un compte utilisateur.'
                ], 400);
            }

            $roles = Role::active()->get();

            return response()->json([
                'member' => $member,
                'success' => true,
                'roles' => $roles
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    /**
     * API pour récupérer les townships d'une ville
     */
    public function getTownshipsByCity($cityId)
    {
        try {

            $townships = Township::where('city_id', $cityId)->get();

            if (!$townships) {
                return response()->json([
                    'success' => false,
                    'message' => 'Township not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'townships' => $townships
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }

    }

    /**
     * Générer automatiquement le numéro de membre
     */
    private function generateMembershipNumber($siteId, $cityId = null)
    {
        return DB::transaction(function () use ($siteId, $cityId) {
            $site = Site::lockForUpdate()->find($siteId);

            // Récupérer le code de la province depuis la ville
            $provinceCode = 'XXX'; // Code par défaut
            if ($cityId) {
                $city = City::find($cityId);
                if ($city && $city->province_code) {
                    $provinceCode = strtoupper($city->province_code);
                }
            }

            // Générer le numéro avec le site
            return $site->generateMembershipNumber($provinceCode);
        });
    }

    /**
     * Gérer l'upload d'image (fichier ou base64)
     */
    private function handleImageUpload(Request $request)
    {
        // Vérifier s'il y a une image en base64 (depuis mobile)
        if ($request->has('face_base64') && !empty($request->face_base64)) {
            return $this->saveBase64Image($request->face_base64);
        }

        // Vérifier s'il y a un fichier uploadé (depuis web)
        if ($request->hasFile('face_image')) {
            return $this->saveUploadedFile($request->file('face_image'));
        }

        return null;
    }

    /**
     * Sauvegarder une image base64
     */
    private function saveBase64Image($base64String)
    {
        try {
            // Extraire les données de l'image base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                $data = substr($base64String, strpos($base64String, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, gif

                if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                    throw new \Exception('Type d\'image non valide');
                }

                $data = base64_decode($data);

                if ($data === false) {
                    throw new \Exception('Échec du décodage base64');
                }
            } else {
                throw new \Exception('Format base64 non valide');
            }

            // Générer un nom de fichier unique
            $fileName = 'profile_' . Str::uuid() . '.' . $type;
            $filePath = 'profiles/' . $fileName;

            // Créer le répertoire s'il n'existe pas
            if (!Storage::disk('public')->exists('profiles')) {
                Storage::disk('public')->makeDirectory('profiles');
            }

            // Sauvegarder le fichier
            Storage::disk('public')->put($filePath, $data);

            return $filePath;

        } catch (\Exception $e) {
            \Log::error('Erreur lors de la sauvegarde de l\'image base64: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sauvegarder un fichier uploadé
     */
    private function saveUploadedFile($file)
    {
        try {
            // Générer un nom de fichier unique
            $fileName = 'profile_' . Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Sauvegarder dans le répertoire profiles
            $filePath = $file->storeAs('profiles', $fileName, 'public');

            return $filePath;

        } catch (\Exception $e) {
            \Log::error('Erreur lors de la sauvegarde du fichier: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * API pour créer/modifier un membre depuis mobile (avec base64)
     */
    public function apiStore(Request $request)
    {
        try {

            $request->validate([
                'firstname' => 'nullable|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'site_id' => 'required|exists:sites,id',
                'city_id' => 'nullable|exists:cities,id',
                'township_id' => 'nullable|exists:townships,id',
                'pool_id' => 'nullable|exists:pools,id',
                'libelle_pool' => 'nullable|string|max:255',
                'fonction_id' => 'nullable|exists:fonctions,id',
                'face_base64' => 'nullable|string',
                'is_active' => 'nullable|boolean'
            ]);

            $data = $request->all();

            // Générer automatiquement le numéro de membre
            $data['membershipNumber'] = $this->generateMembershipNumber($request->site_id, $request->city_id);

            // Gérer l'image base64
            if ($request->has('face_base64') && !empty($request->face_base64)) {
                $data['face_path'] = $this->saveBase64Image($request->face_base64);
            }

            $member = Member::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Membre créé avec succès',
                'member' => $member->load('site', 'city', 'township', 'pool', 'fonction')
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

    /**
     * API pour modifier un membre depuis mobile
     */
    public function apiUpdate(Request $request)
    {
        try {

            $request->validate([
                'firstname' => 'nullable|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'site_id' => 'required|exists:sites,id',
                'city_id' => 'nullable|exists:cities,id',
                'township_id' => 'nullable|exists:townships,id',
                'pool_id' => 'nullable|exists:pools,id',
                'libelle_pool' => 'nullable|string|max:255',
                'fonction_id' => 'nullable|exists:fonctions,id',
                'face_base64' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'member_id' => 'required|exists:members,id'
            ]);

            $member = Member::findOrFail($request->member_id);

            if(!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre non trouvé'
                ], 404);
            }

            $data = $request->all();

            // Régénérer le numéro de membre si le site ou la ville a changé
            if ($request->site_id != $member->site_id || $request->city_id != $member->city_id) {
                $data['membershipNumber'] = $this->generateMembershipNumber($request->site_id, $request->city_id);
            }

            // Gérer l'image base64
            if ($request->has('face_base64') && !empty($request->face_base64)) {
                // Supprimer l'ancienne image
                if ($member->face_path && Storage::disk('public')->exists($member->face_path)) {
                    Storage::disk('public')->delete($member->face_path);
                }
                $data['face_path'] = $this->saveBase64Image($request->face_base64);
            }

            $member->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Membre mis à jour avec succès',
                'data' => $member->load('site', 'city', 'township', 'pool', 'fonction')
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => "Erreur, " .$th->getMessage()
            ], 500);
        }
    }

}
