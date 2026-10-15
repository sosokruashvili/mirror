<?php

namespace App\Models;

use App\Models\Concerns\QueuesNotifications;
use App\Services\CabinetAccess;
use App\Support\Cabinet\Cabinet;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Client extends Model
{
    use CrudTrait;
    use HasFactory;
    use Notifiable;
    use QueuesNotifications;

    /**
     * Phone number used by the SMS notification channel.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone_number ?: null;
    }

    protected $table = 'clients';
    protected $guarded = ['id'];
    
    protected $fillable = [
        'name',
        'email',
        'client_type',
        'personal_id',
        'legal_id',
        'address',
        'phone_number',
        'starting_balance'
    ];

    protected $casts = [
        'client_type' => 'boolean',
        'starting_balance' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        // Match orders.author: stamp the creating admin on insert (CRUD + AJAX).
        // Only set on insert, so later edits keep the original author.
        static::creating(function (Client $client) {
            if ($client->author === null && function_exists('backpack_user') && backpack_user()) {
                $client->author = backpack_user()->id;
            }
        });

        static::saving(function ($client) {
            foreach (['personal_id', 'legal_id', 'email'] as $field) {
                if ($client->{$field} === '') {
                    $client->{$field} = null;
                }
            }

            // Cabinet credentials are stored on client_accounts, never here.
            unset($client->cabinet_enabled, $client->cabinet_password, $client->cabinet_password_confirmation);
        });
    }

    /**
     * The cabinet login for this client, when staff have created one.
     */
    public function cabinetAccount()
    {
        return $this->hasOne(ClientAccount::class);
    }

    /**
     * The user who created the client (stored on clients.author).
     *
     * Named authorUser() rather than author() on purpose: a relationship method
     * that shares its name with the real `author` column collides, and Eloquent
     * returns the raw column value instead of loading the relation.
     */
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
    }

    /**
     * Get the display name with ID for select options.
     * Shows name followed by legal_id or personal_id (whichever is not empty).
     */
    public function getNameWithIdAttribute()
    {
        $id = $this->legal_id ?: $this->personal_id;
        if ($id) {
            return $this->name . ' (' . $id . ')';
        }
        return $this->name;
    }

    /**
     * Georgian text of the cabinet-access SMS. Always Georgian, whatever
     * language the admin panel is using, because the client reads it.
     */
    /**
     * The cabinet-access SMS, rendered from the system template.
     */
    public function cabinetSmsBody(string $url): string
    {
        return SmsTemplate::findByKey(SmsTemplate::KEY_CABINET_ACCESS)->render([
            'cabinet_url' => $url,
        ], $this);
    }

    /**
     * Turn cabinet access on and queue the SMS with this client's private link.
     */
    public function allowCabinet(): Notification
    {
        return app(CabinetAccess::class)->allow($this);
    }

    /**
     * The orders that belong to the client.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The payments that belong to the client.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The daily balance snapshots for the client.
     */
    public function balances()
    {
        return $this->hasMany(ClientBalance::class);
    }

    /**
     * The most recent daily balance snapshot for the client.
     */
    public function latestBalance()
    {
        return $this->hasOne(ClientBalance::class)->latestOfMany('balance_date');
    }

    /**
     * The "as of date" balance for the client: the most recent snapshot whose
     * balance_date is on or before {@see static::$balanceAsOfDate}. Used by the
     * client balances screen's date filter. When no date is set this behaves
     * like {@see latestBalance()} (no upper bound on the date).
     */
    public function balanceForDate()
    {
        return $this->hasOne(ClientBalance::class)->ofMany(
            ['balance_date' => 'max'],
            function ($query) {
                if (static::$balanceAsOfDate) {
                    // balance_date is a date column, so this is equivalent to
                    // whereDate() without emitting a cast the planner has to
                    // see through before it can use the index.
                    $query->where('balance_date', '<=', static::$balanceAsOfDate);
                }
            }
        );
    }

    /**
     * The "as of" date (Y-m-d) used by the {@see balanceForDate()} relationship.
     * Set by ClientBalanceCrudController before the query runs so eager loading
     * picks the right historical snapshot. Null means "latest snapshot".
     *
     * @var string|null
     */
    public static $balanceAsOfDate = null;

    /**
     * Calculate the client's balance.
     * Balance = starting balance + sum of paid payments - sum of orders total price (excluding draft orders)
     *
     * @return float
     */
    public function calculateBalance()
    {
        // Manually entered opening balance carried over from before the system.
        $startingBalance = (float) ($this->starting_balance ?? 0);

        // Only count payments with status 'Paid'
        $paymentsSum = $this->payments()->where('status', 'Paid')->sum('amount_gel') ?? 0;

        // Only count orders that are not in 'draft' status
        $ordersSum = $this->orders()->where('status', '!=', 'draft')->get()->sum(function($order) {
            // Read-only: never write piece prices back while reading a balance.
            return $order->calculateTotalPrice(false);
        });

        return $startingBalance + $paymentsSum - $ordersSum;
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }
}
