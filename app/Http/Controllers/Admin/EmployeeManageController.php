<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopPasswordResetRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\UserNonPermission;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class EmployeeManageController extends Controller
{

public function index(Request $request)
{
    $notNeedRoles = ['shop', 'customer', 'driver'];
    
    // Get the filter and search values from the request
    $roleFilter = $request->input('role'); // Filter by role
    $searchTerm = $request->input('search'); // Search by name or phone number

    // Start the query with roles filter
    $usersQuery = User::whereHas('roles', function ($q) use ($notNeedRoles) {
        $q->whereNotIn('name', $notNeedRoles);
    })->whereNull('shop_id')->with('roles');
    
    // Apply role filter if provided
    if ($roleFilter) {
        $usersQuery->whereHas('roles', function ($q) use ($roleFilter) {
            $q->where('name', $roleFilter);
        });
    }
    
    // Apply search by name or phone number if provided
    if ($searchTerm) {
        $usersQuery->where(function ($query) use ($searchTerm) {
            $query->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'like', '%' . $searchTerm . '%');
        });
    }

    // Paginate the results
    $users = $usersQuery->paginate(20);

    // Return the view with the filtered and searched users
    return view('admin.employee.index', compact('users'));
}


    public function create()
    {
        $notNeedRoles = ['shop', 'customer', 'driver'];

        $roles = Role::whereNotIn('name', $notNeedRoles)->get();

        return view('admin.employee.create', compact('roles'));
    }

    public function store(UserRequest $request)
{
    $user = User::where('phone', $request->phone)->first();
    if ($user) {
        return back()->withError(__('Phone number already exists'));
    }

    $request['is_active'] = true;
    $user = UserRepository::storeByRequest($request);

    // Assign the role to the user
    $user->assignRole($request->role);

    // Save the user to the plumbers table if the role is plumber
    if ($request->role === 'plumber') {
    \App\Models\Plumber::create([
        'user_id' => $user->id,  // Only the user_id field is being populated
    ]);
}


    // Store wallet data for the user
    WalletRepository::storeByRequest($user);

    return to_route('admin.employee.index')->withSuccess(__('Created successfully'));
}


    public function resetPassword(User $user, ShopPasswordResetRequest $request)
    {
        // Update the user password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->withSuccess(__('Updated successfully'));
    }

    public function destroy(User $user)
    {
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $media = $user->media;

        if ($media && Storage::exists($media->src)) {
            Storage::delete($media->src);
        }

        $user->wallet()?->delete();
        $user->forceDelete();

        if ($media) {
            $media->delete();
        }

        return back()->withSuccess(__('Deleted successfully'));
    }

    public function permission(User $user)
    {
        $generaleSetting = generaleSetting();

        $userRole = $user->getRoleNames()->toArray()[0];

        $role = Role::where('name', $userRole)->first();

        $rolePermissions = $role->getPermissionNames()->toArray();
        $userPermissions = $user->getPermissionNames()->toArray();

        $userNonPermissions = UserNonPermission::where('user_id', $user->id)->pluck('name')->toArray();

        $allPermissions = array_merge($userPermissions, $rolePermissions);
        $allPermissions = array_unique($allPermissions);

        $allPermissionArray = [];

        if ($generaleSetting?->shop_type == 'single') {
            $allPermissionArray['shop'] = config('acl.permissions.shop');
            $allPermissionArray['admin'] = config('acl.permissions.admin');
        } else {
            $allPermissionArray['adminMultiShop'] = config('acl.permissions.adminMultiShop');
            $allPermissionArray['shop'] = config('acl.permissions.shop');
            $allPermissionArray['admin'] = config('acl.permissions.admin');
        }

        $userAvailablePermissions = array_diff($allPermissions, $userNonPermissions);

$roles = Role::whereNotIn('name', ['shop', 'customer', 'driver'])->get();

return view('admin.employee.permission', compact(
    'user',
    'role',
    'allPermissionArray',
    'userAvailablePermissions',
    'roles'
));
    }

    public function updatePermission(User $user, Request $request)
    {
        $permissisons = $request->permissions ?? [];

        $role = Role::where('id', $request->role_id)->first();
        $rolePermissions = $role->getPermissionNames()->toArray();

        $customPermissions = [];
        $removePermissions = [];

        foreach ($permissisons as $permission) {
            if (! in_array($permission, $rolePermissions)) {
                $customPermissions[] = $permission;
            }
        }

        foreach ($rolePermissions ?? [] as $permission) {
            if (! in_array($permission, $permissisons)) {
                $removePermissions[] = $permission;
            }
        }

        try {
            $user->syncPermissions($customPermissions);
        } catch (\Exception $e) {
            return back()->withError($e->getMessage());
        }

        UserNonPermission::where('user_id', $user->id)->delete();

        foreach ($removePermissions as $permission) {
            UserNonPermission::create([
                'user_id' => $user->id,
                'name' => $permission,
            ]);
        }

        Cache::forget('user_permissions_'.$user->id);
        Cache::forget('user_non_permissions_'.$user->id);

        return to_route('admin.employee.index')->withSuccess(__('Permission Updated Successfully'));
    }
public function update(Request $request, $id)
{
    // Validate the incoming data
    $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:15',
        'email' => 'required|email|max:255',
        'role' => 'required|exists:roles,name',
    ]);

    // Find the user by ID
    $user = User::findOrFail($id);

    // Update user basic details
    $user->update([
        'name' => $request->name,
        'phone' => $request->phone,
        'email' => $request->email,
    ]);

    // Sync the role using Spatie method
    $user->syncRoles([$request->role]);

    return redirect()->route('admin.employee.index')->with('success', 'User updated successfully');
}


}
