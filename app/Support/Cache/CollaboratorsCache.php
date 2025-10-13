<?php

namespace App\Support\Cache;

use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

/*
* Classe helper para lidar com cache de colaboradores por usuário.
* Usa cache tags para facilitar a invalidação. Vi esse padrão em alguns projetos.
* Gosto de usar esse padrão quando quero agrupar caches relacionados
* e facilitar a limpeza de caches específicos
*/
final class CollaboratorsCache
{
    public static function tagForUser(int $userId): string
    {
        return sprintf('users:%d:collaborators', $userId);
    }

    public static function storeForUser(int $userId): TaggedCache
    {
        return Cache::tags(self::tagForUser($userId));
    }

    public static function flushForUser(int $userId): void
    {
        self::storeForUser($userId)->flush();
    }
}
