<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;



class RolePermissionController extends Controller
{
    public function index()
    {
        $notNeedRoles = ['shop', 'customer', 'driver'];

        $roles = Role::whereNotIn('name', $notNeedRoles)->with('permissions')->get();

        return view('admin.role-permission.index', compact('roles'));
    }

    public function store(RoleRequest $request)
{
    $role = Role::create([
        'name' => $request->name,
        'guard_name' => 'web',
        'is_shop' => $request->for_shop ? true : false,
    ]);

    // Assign selected permissions (if provided)
    if ($request->has('permissions')) {
        $role->syncPermissions($request->permissions);
    }

    return redirect()->route('admin.role.index')->withSuccess(__('Role Created Successfully'));
}


public function showAddPermissionForm()
{
    $permissions = Permission::paginate(10); // Get all existing permissions
    return view('admin.role-permission.add-permission', compact('permissions'));
}

public function deletePermission($id)
{
    $permission = Permission::find($id);

    if (!$permission) {
        return back()->with('alertError', __('Permission not found'));
    }

    $permission->delete();

    return back()->withSuccess(__('Permission Deleted Successfully'));
}
public function addPermission(Request $request)
{
    $request->validate([
        'name' => 'required|string|unique:permissions,name',
    ]);

    // Create a new permission
    $permission = Permission::create([
        'name' => $request->name,
        'guard_name' => 'web',
    ]);

    return back()->withSuccess(__('Permission Created Successfully'));
}
    public function update(RoleRequest $request, Role $role)
    {
        $role->update([
            'name' => $request->name,
            'is_shop' => $request->for_shop ? true : false,
        ]);

        return back()->withSuccess(__('Updated Successfully'));
    }

    public function destroy(Role $role)
    {
        if ($role->name == 'root') {
            return back()->withError(__('You can not delete root role'));
        }

        $role->syncPermissions([]);

        $users = $role->users;

        foreach ($users as $user) {
            $user->syncRoles([]);
            $user->syncPermissions([]);

            $media = $user->media;

            if ($media && Storage::exists($media->src)) {
                Storage::delete($media->src);
            }

            $user->wallet()?->delete();

            $user->tokens()->delete();

            $user->forceDelete();

            if ($media) {
                $media->delete();
            }
        }

        $role->delete();

        return to_route('admin.role.index')->withSuccess(__('Deleted Successfully'));
    }

 

public function rolePermission(Role $role)
{
    $generaleSetting = generaleSetting('setting');
    $allPermissionArray = [];

    // 1️⃣ **Fetch predefined permissions from config**
    if ($generaleSetting?->shop_type == 'single') {
        if ($role->is_shop) {
            $allPermissionArray['shop'] = config('acl.permissions.shop');
        } else {
            $allPermissionArray['shop'] = config('acl.permissions.shop');
            $allPermissionArray['admin'] = config('acl.permissions.admin');
        }
    } else {
        if ($role->is_shop) {
            $allPermissionArray['shop'] = config('acl.permissions.shopMultiShop');
            $allPermissionArray['shop'] = config('acl.permissions.shop');
        } else {
            $allPermissionArray['adminMultiShop'] = config('acl.permissions.adminMultiShop');
            $allPermissionArray['shop'] = config('acl.permissions.shop');
            $allPermissionArray['admin'] = config('acl.permissions.admin');
        }
    }

    // 2️⃣ **Fetch newly added permissions from database**
    $databasePermissions = Permission::all()->groupBy(function ($permission) {
        return explode('.', $permission->name)[0]; // Group by category
    });

    // 3️⃣ **Merge predefined permissions with newly added ones**
    foreach ($databasePermissions as $category => $permissions) {
        foreach ($permissions as $permission) {
            $allPermissionArray[$category][$permission->name][] = $permission->name;
        }
    }

    // 4️⃣ **Fetch roles & assigned permissions**
    $notNeedRoles = ['shop', 'customer', 'driver'];
    $rolesQuery = Role::whereNotIn('name', $notNeedRoles)->with('permissions');

    if (request()->has('fst')) {
        $rolesQuery->orderByRaw('id = ? DESC', [request('fst')]);
    }
    $roles = $rolesQuery->get();

    $permissions = $role->permissions->pluck('name')->toArray();
    $activeRole = $role->name;
    $selectedRole = $role; // Ensure this variable exists

    return view('admin.role-permission.index', compact('selectedRole', 'permissions', 'roles', 'activeRole', 'allPermissionArray'));
}

   public function updateRolePermission(Request $request, Role $role)
{
    try {
        $role->syncPermissions($request->permissions ?? []);
    } catch (\Throwable $th) {
        return back()->with('alertError', [
            'message' => $th->getMessage(),
            'message2' => 'Please run PermissionSeeder and try again. Run "php artisan db:seed --class=PermissionSeeder"',
        ]);
    }

    Cache::forget('role_permissions_'.$role->id);

    return back()->withSuccess(__('Permissions Updated Successfully'));
}

}
