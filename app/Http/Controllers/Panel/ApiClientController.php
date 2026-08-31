<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cadastro das credenciais usadas pela API. O segredo em claro aparece uma
 * única vez, logo após a criação ou a rotação.
 */
class ApiClientController extends Controller
{
    public function index(): View
    {
        return view('clients.index', [
            'clients' => ApiClient::withCount('tasks')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'allowed_store_uuids' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'nome']);

        $stores = collect(preg_split('/[\s,;]+/', (string) ($data['allowed_store_uuids'] ?? '')))
            ->filter()
            ->values()
            ->all();

        [$client, $secret] = ApiClient::issue([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'allowed_store_uuids' => $stores ?: null,
            'is_active' => true,
            'created_by_email' => $request->user()->email,
        ]);

        return back()->with('status', 'Cliente criado.')->with('new_secret', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'name' => $client->name,
        ]);
    }

    public function rotate(Request $request, ApiClient $client): RedirectResponse
    {
        $secret = $client->rotateSecret();

        return back()->with('status', 'Segredo rotacionado.')->with('new_secret', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'name' => $client->name,
        ]);
    }

    public function toggle(ApiClient $client): RedirectResponse
    {
        $client->forceFill(['is_active' => ! $client->is_active])->save();

        return back()->with('status', $client->is_active ? 'Cliente ativado.' : 'Cliente desativado.');
    }

    public function destroy(ApiClient $client): RedirectResponse
    {
        $client->delete();

        return back()->with('status', 'Cliente removido.');
    }
}
