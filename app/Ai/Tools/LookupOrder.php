<?php

namespace App\Ai\Tools;

use App\Services\ReplacementCaseService;
use App\Support\HarborReplacementCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LookupOrder implements Tool
{
    public function __construct(
        public string $caseId,
        public int $generation,
    ) {}

    public function description(): Stringable|string
    {
        return 'Look up a fictional Harbor order by order number or email. Use this to see the item, delivery, and whether photos are on file. This does not save a replacement.';
    }

    public function handle(Request $request): Stringable|string
    {
        $number = strtoupper(trim((string) ($request['order_number'] ?? '')));
        $email = strtolower(trim((string) ($request['email'] ?? '')));
        $activity = app(ReplacementCaseService::class);

        if ($number === '' && $email === '') {
            return 'An order number or email is needed before the order can be checked.';
        }

        $activity->recordActivity($this->caseId, $this->generation, 'Checking your order');

        if ($number !== '') {
            $order = HarborReplacementCatalog::find($number);

            if ($order === null) {
                $activity->recordActivity($this->caseId, $this->generation, 'We could not find that order.');

                return 'We could not find that order.';
            }

            $activity->recordActivity($this->caseId, $this->generation, 'Order '.$order['number']);

            return $this->describe($order);
        }

        $matches = HarborReplacementCatalog::findByEmail($email);

        if ($matches === []) {
            $activity->recordActivity($this->caseId, $this->generation, 'We could not find that order.');

            return 'We could not find that order.';
        }

        if (count($matches) > 1) {
            $numbers = implode(', ', array_map(fn (array $order): string => $order['number'], $matches));

            return 'More than one order uses that email: '.$numbers.'. Ask which order the visitor means.';
        }

        $activity->recordActivity($this->caseId, $this->generation, 'Order '.$matches[0]['number']);

        return $this->describe($matches[0]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->nullable(),
            'email' => $schema->string()->nullable(),
        ];
    }

    /**
     * @param  array{
     *     number: string,
     *     customer_name: string,
     *     email: string,
     *     sku: string,
     *     item: string,
     *     quantity: int,
     *     delivered_days_ago: int,
     *     photos_on_file: bool
     * }  $order
     */
    private function describe(array $order): string
    {
        $photos = $order['photos_on_file'] ? 'Photos of the carton and item are on file.' : 'Photos of the carton and item are not on file.';

        return implode("\n", [
            'Order '.$order['number'],
            'Customer: '.$order['customer_name'],
            'Item: '.$order['item'],
            'Quantity: '.$order['quantity'],
            'Delivered '.$order['delivered_days_ago'].' days ago.',
            $photos,
            'This is a fictional demo order. Nothing is shipped.',
        ]);
    }
}
