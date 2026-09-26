# FastWay SMM Provider Contract

**Status:** Primary provider for all new service catalogue requests and orders.

## Provider endpoint

| Setting | Value |
| --- | --- |
| API URL | `https://fastwaysmm.com/api/v2` |
| Transport | `POST` |
| Content type | `application/x-www-form-urlencoded` |
| Authentication | `key` form parameter, supplied only by `FASTWAY_API_KEY` in the deployment environment |
| Supplier currency | USD per 1,000 units |
| Customer currency | TZS |
| Customer markup | **78%** over the converted supplier price |

> The application does not embed the FastWay credential in tracked source. Configure `FASTWAY_API_KEY` in the host environment (or an ignored local `.env` file).

## Implemented FastWay actions

| Application operation | FastWay request payload | Local behavior |
| --- | --- | --- |
| Load services | `key`, `action=services` | Caches the normalized catalogue for one hour and applies the 78% customer markup. |
| Place order | `key`, `action=add`, `service`, `link`, `quantity` | Calls FastWay before deducting customer balance; an upstream API error does not create or charge an order. |
| Check order | `key`, `action=status`, `order` | Synchronizes non-final orders when the customer views orders. |
| Check balance | `key`, `action=balance` | Converts FastWay USD balance to TZS for the administrator's provider-balance display. |
| Refill | `key`, `action=refill`, `order` | Requests an eligible refill directly and stores the FastWay refill ID in the existing refill status trail. |
| Cancel | `key`, `action=cancel`, `orders` | Sends cancellation for services FastWay marks as cancellable. |

## Pricing formula

```text
supplier_tzs_per_1000 = fastway_usd_rate_per_1000 × USD_TO_TZS_RATE
customer_tzs_per_1000 = supplier_tzs_per_1000 × 1.78
```

The displayed per-unit rate is `customer_tzs_per_1000 / 1000`, rounded consistently with the existing order form. `PRICE_MARKUP_PERCENT` is set to `78` in `config.php`; a versioned catalogue cache key invalidates old 60% pricing.

## Compatibility and rollout

- **New orders** are always created through FastWay and persist `provider = fastway`.
- **Existing orders** retain their saved provider so legacy Boost order status lookup is not redirected to FastWay accidentally.
- The existing screens, forms, styling, and customer flow are unchanged. The provider migration is backend-only.
- Render deployment declares `FASTWAY_API_KEY` as a secret environment variable. Add the actual credential in the Render service settings before deploying.

## Source

Implementation follows the official [FastWay SMM API documentation](https://fastwaysmm.com/api), which specifies form-encoded POST requests to `/api/v2` for services, add order, status, refill, cancel, and balance.
