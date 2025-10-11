<?php

namespace App\Models;

use App\Models\Builders\CollaboratorBuilder;
use App\ValueObjects\BrazilianDocument;
use Database\Factories\CollaboratorFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UnexpectedValueException;

// sem Fat Models cheias de regras de negócio, gosto de delegar essas regras para serviços/repositories/actions/custom query builders
// aqui temos apenas as regras de acesso aos dados e algumas formatações simples

/**
 * @property int $user_id
 */
#[UseEloquentBuilder(CollaboratorBuilder::class)]
class Collaborator extends Model
{
    /** @use HasFactory<CollaboratorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cpfFormatted',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'cpf',
        'city',
        'state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cpf' => BrazilianDocument::class,
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static function (mixed $value): string {
                if (! is_string($value)) {
                    throw new UnexpectedValueException('Email inválido.');
                }

                return strtolower($value);
            },
        );
    }

    /**
     * @return Attribute<BrazilianDocument|null, string>
     */
    protected function cpf(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): ?BrazilianDocument {
                if (! is_string($value) || $value === '') {
                    return null;
                }

                return BrazilianDocument::from($value);
            },
            set: static function (mixed $value): string {
                if ($value instanceof BrazilianDocument) {
                    return $value->value();
                }

                if (! is_string($value)) {
                    throw new UnexpectedValueException('Documento inválido.');
                }

                $document = BrazilianDocument::from($value);

                return $document->value();
            },
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function cpfFormatted(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value, array $attributes): ?string {
                $document = $attributes['cpf'] ?? $value;

                if (! is_string($document) || $document === '') {
                    return null;
                }

                return BrazilianDocument::from($document)->formatted();
            },
        );
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Forma antiga de dar override no Eloquent Builder, agora temos como usar  attributes a partir da v12.19 (acima da class)
    // Tenho usado cada vez mais esse recurso ao invés de Repositories.
    // Ref: https://medium.com/@iago3220/why-you-dont-need-the-repository-pattern-in-laravel-a-look-at-custom-builders-537679e85251
    // /**
    //  * @param  QueryBuilder  $query
    //  * @return CollaboratorBuilder<self>
    //  */
    // public function newEloquentBuilder($query): CollaboratorBuilder
    // {
    //     return new CollaboratorBuilder($query);
    // }
}
