<?php

namespace App\Request;

use Illuminate\Support\Facades\Http;
use App\Models\OptionsGeneral;
use App\Models\OptionsAzuriom;

class AzuriomApi
{
    private $baseUrl;
    private $apiKey;

    public function __construct(?OptionsAzuriom $site = null)
    {
        if ($site === null) {
            $site = OptionsAzuriom::where('is_primary', true)->first()
                 ?? OptionsAzuriom::first();
        }

        if ($site !== null) {
            if (!$site->url) {
                throw new \RuntimeException("L'URL Azuriom doit être configurée dans les paramètres.");
            }
            $this->baseUrl = rtrim($site->url, '/');
            $this->apiKey  = $site->api_key ?? '';
        } else {
            // Fallback: backward compat with OptionsGeneral
            $options = OptionsGeneral::first();
            if (!$options) {
                throw new \RuntimeException("Les options générales ne sont pas configurées. Veuillez configurer l'URL Azuriom et la clé API dans les paramètres généraux.");
            }
            if (!$options->azuriom_url || !$options->azuriom_api_key) {
                throw new \RuntimeException("L'URL Azuriom et la clé API doivent être configurées dans les paramètres généraux.");
            }
            $this->baseUrl = rtrim($options->azuriom_url, '/');
            $this->apiKey  = $options->azuriom_api_key;
        }
    }

    public static function forServer(int $serverId): self
    {
        $site = OptionsAzuriom::where('server_id', $serverId)->first()
             ?? OptionsAzuriom::where('is_primary', true)->first()
             ?? OptionsAzuriom::first();

        return new self($site);
    }

    private function makeRequest($endpoint)
    {
        return Http::withOptions([
            'verify' => false
        ])->withHeaders([
            'API-Key' => $this->apiKey
        ])->get($this->baseUrl . $endpoint);
    }

    public function getServers()
    {
        return $this->makeRequest('/api/apiextender/servers');
    }

    public function getRoles()
    {
        $response = $this->makeRequest('/api/apiextender/roles');
        if ($response->successful()) {
            return $response->json();
        }
        return [];
    }

    public function getUsers()
    {
        $response = $this->makeRequest('/api/apiextender/users');
        if ($response->successful()) {
            return $response->json();
        }
        return [];
    }

    public function getMoney()
    {
        return $this->makeRequest('/api/apiextender/money');
    }

    public function getSocial()
    {
        return $this->makeRequest('/api/apiextender/social');
    }
}
