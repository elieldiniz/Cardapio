<?php

use App\Models\AdminAction;
use App\Models\Badge;
use App\Models\DiscountType;
use App\Models\DishStatus;
use App\Models\GenerationLedgerType;
use App\Models\GenerationStatus;
use App\Models\MetricsLevel;
use App\Models\RestaurantStatus;
use App\Models\Role;
use App\Models\VideoOrigin;
use App\Models\VideoStatus;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\DiscountTypeSeeder;
use Database\Seeders\DishStatusSeeder;
use Database\Seeders\GenerationLedgerTypeSeeder;
use Database\Seeders\GenerationStatusSeeder;
use Database\Seeders\MetricsLevelSeeder;
use Database\Seeders\RestaurantStatusSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VideoOriginSeeder;
use Database\Seeders\VideoStatusSeeder;

function lookupSeederExpectations(): array
{
    return [
        Role::class => [RoleSeeder::class, ['dono', 'super_admin']],
        RestaurantStatus::class => [RestaurantStatusSeeder::class, ['ativo', 'suspenso']],
        DishStatus::class => [DishStatusSeeder::class, ['ativo', 'esgotado', 'oculto']],
        VideoStatus::class => [VideoStatusSeeder::class, ['processando', 'aguardando_aprovacao', 'aprovado', 'rejeitado']],
        VideoOrigin::class => [VideoOriginSeeder::class, ['ia', 'upload']],
        GenerationStatus::class => [GenerationStatusSeeder::class, ['fila', 'gerando', 'pronto', 'erro']],
        GenerationLedgerType::class => [GenerationLedgerTypeSeeder::class, ['renovacao', 'compra', 'uso', 'estorno']],
        MetricsLevel::class => [MetricsLevelSeeder::class, ['cardapio_total', 'por_prato', 'por_prato_com_tempo_assistido']],
        DiscountType::class => [DiscountTypeSeeder::class, ['percentual', 'valor_fixo']],
        Badge::class => [BadgeSeeder::class, ['novo', 'mais_pedido', 'vegetariano']],
        AdminAction::class => [AdminActionSeeder::class, [
            'restaurante_suspenso',
            'restaurante_reativado',
            'restaurante_impersonado',
            'conteudo_removido',
            'plano_atualizado',
            'cupom_criado',
            'conta_restaurada',
            'conta_excluida_definitivamente',
        ]],
    ];
}

it('each lookup table seeds exactly the documented slugs', function () {
    foreach (lookupSeederExpectations() as $modelClass => [$seederClass, $expectedSlugs]) {
        $this->seed($seederClass);

        expect($modelClass::pluck('slug')->sort()->values()->all())
            ->toBe(collect($expectedSlugs)->sort()->values()->all());
    }
});

it('re running every seeder does not create duplicate rows', function () {
    foreach (lookupSeederExpectations() as $modelClass => [$seederClass, $expectedSlugs]) {
        $this->seed($seederClass);
        $this->seed($seederClass);

        expect($modelClass::count())->toBe(count($expectedSlugs));
    }
});
