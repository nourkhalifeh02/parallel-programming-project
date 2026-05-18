// app/Http/Middleware/CapacityControlMiddleware.php

use App\Services\ResourceManager;
use App\Exceptions\CapacityExceededException;

class CapacityControlMiddleware
{
    public function __construct(private ResourceManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->manager->executeWithCapacityControl(
                fn() => $next($request)
            );
        } catch (CapacityExceededException $e) {
            return response()->json([
                'error'       => 'Server at capacity, try again shortly',
                'active'      => $e->active,
                'limit'       => $e->limit,
            ], 503)->header('Retry-After', 3);
        }
    }
}