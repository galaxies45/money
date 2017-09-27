<?php

namespace Brick\Money;

use Brick\Money\Context\DefaultContext;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Brick\Math\Exception\ArithmeticException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;

/**
 * A monetary value in a given currency. This class is immutable.
 *
 * A Money has an amount, a currency, and a context. The context defines the scale of the amount, and an optional cash
 * rounding step, for monies that do not have coins or notes for their smallest units.
 *
 * All operations on a Money return another Money with the same context. The available contexts are:
 *
 * - DefaultContext handles monies with the default scale for the currency.
 * - CashContext is similar to DefaultContext, but supports a cash rounding step.
 * - PrecisionContext handles monies with a custom scale, and optionally step.
 * - ExactContext always returns an exact result, adjusting the scale as required by the operation.
 */
class Money
{
    /**
     * The amount.
     *
     * @var \Brick\Math\BigDecimal
     */
    private $amount;

    /**
     * The currency.
     *
     * @var \Brick\Money\Currency
     */
    private $currency;

    /**
     * The context that defines the capability of this Money.
     *
     * @var Context
     */
    private $context;

    /**
     * @param BigDecimal $amount
     * @param Currency   $currency
     * @param Context    $context
     */
    private function __construct(BigDecimal $amount, Currency $currency, Context $context)
    {
        $this->amount   = $amount;
        $this->currency = $currency;
        $this->context  = $context;
    }

    /**
     * Returns the minimum of the given monies.
     *
     * If several monies are equal to the minimum value, the first one is returned.
     *
     * @param Money    $money  The first money.
     * @param Money ...$monies The subsequent monies.
     *
     * @return Money
     *
     * @throws MoneyMismatchException If all the monies are not in the same currency.
     */
    public static function min(Money $money, Money ...$monies)
    {
        $min = $money;

        foreach ($monies as $money) {
            if ($money->isLessThan($min)) {
                $min = $money;
            }
        }

        return $min;
    }

    /**
     * Returns the maximum of the given monies.
     *
     * If several monies are equal to the maximum value, the first one is returned.
     *
     * @param Money    $money  The first money.
     * @param Money ...$monies The subsequent monies.
     *
     * @return Money
     *
     * @throws MoneyMismatchException If all the monies are not in the same currency.
     */
    public static function max(Money $money, Money ...$monies)
    {
        $max = $money;

        foreach ($monies as $money) {
            if ($money->isGreaterThan($max)) {
                $max = $money;
            }
        }

        return $max;
    }

    /**
     * Returns the total of the given monies.
     *
     * The monies must share the same currency and context.
     *
     * @param Money    $money  The first money.
     * @param Money ...$monies The subsequent monies.
     *
     * @return Money
     *
     * @throws MoneyMismatchException If all the monies are not in the same currency and context.
     */
    public static function total(Money $money, Money ...$monies)
    {
        $total = $money;

        foreach ($monies as $money) {
            $total = $total->plus($money);
        }

        return $total;
    }

    /**
     * Creates a Money from a rational amount, a currency, and a context.
     *
     * @param BigNumber $amount
     * @param Currency  $currency
     * @param Context   $context
     * @param int       $roundingMode
     *
     * @return Money
     */
    public static function create(BigNumber $amount, Currency $currency, Context $context, $roundingMode)
    {
        $amount = $context->applyTo($amount, $currency, $roundingMode);

        return new Money($amount, $currency, $context);
    }

    /**
     * Returns a Money of the given amount and currency.
     *
     * By default, the money is created with a DefaultContext. This means that the amount is scaled to match the
     * currency's default fraction digits. For example, `Money::of('2.5', 'USD')` will yield `USD 2.50`.
     * If the amount cannot be safely converted to this scale, an exception is thrown.
     *
     * To override this behaviour, a Context instance can be provided.
     * Operations on this Money return a Money with the same context.
     *
     * @param BigNumber|number|string  $amount         The monetary amount.
     * @param Currency|string         $currency     The currency, as a Currency instance or ISO currency code.
     * @param Context|null            $context      An optional Context.
     * @param int                     $roundingMode An optional RoundingMode, if the amount does not fit the context.
     *
     * @return Money
     *
     * @throws NumberFormatException      If the amount is a string in a non-supported format.
     * @throws RoundingNecessaryException If the rounding was necessary to represent the amount at the requested scale.
     */
    public static function of($amount, $currency, Context $context = null, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $currency = Currency::of($currency);

        if ($context === null) {
            $context = new DefaultContext();
        }

        $amount = BigNumber::of($amount);

        return self::create($amount, $currency, $context, $roundingMode);
    }

    /**
     * Returns a Money from a number of minor units.
     *
     * The result is a Money with a DefaultContext: this Money has the default scale for the currency.
     *
     * @param BigNumber|number|string $minorAmount The amount in minor units. Must be convertible to a BigInteger.
     * @param Currency|string         $currency    The currency, as a Currency instance or ISO currency code.
     *
     * @return Money
     *
     * @throws UnknownCurrencyException If the currency is an unknown currency code.
     * @throws ArithmeticException      If the amount cannot be converted to a BigInteger.
     */
    public static function ofMinor($minorAmount, $currency)
    {
        $currency = Currency::of($currency);

        $amount = BigDecimal::ofUnscaledValue($minorAmount, $currency->getDefaultFractionDigits());

        return new Money($amount, $currency, new DefaultContext());
    }

    /**
     * Creates a Money from a RationalMoney and a Context.
     *
     * @param RationalMoney $money
     * @param Context       $context
     * @param int           $roundingMode
     *
     * @return Money
     */
    public static function ofRational(RationalMoney $money, Context $context, $roundingMode = RoundingMode::UNNECESSARY)
    {
        return self::create($money->getAmount(), $money->getCurrency(), $context, $roundingMode);
        }

    /**
     * Returns a Money with zero value, in the given currency.
     *
     * By default, the money is created with a DefaultContext: it has the default scale for the currency.
     * A Context instance can be provided to override the default.
     *
     * @param Currency|string $currency The currency, as a Currency instance or ISO currency code.
     * @param Context|null    $context  An optional context.
     *
     * @return Money
     */
    public static function zero($currency, Context $context = null)
    {
        $currency = Currency::of($currency);

        if ($context === null) {
            $context = new DefaultContext();
        }

        $amount = BigDecimal::zero();

        return self::create($amount, $currency, $context, RoundingMode::UNNECESSARY);
    }

    /**
     * Returns the amount of this Money, as a BigDecimal.
     *
     * @return BigDecimal
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Returns the amount of this Money in minor units (cents) for the currency.
     *
     * The value is returned as a BigDecimal. If this Money has a scale greater than that of the currency, the result
     * will have a non-zero scale.
     *
     * For example, `USD 1.23` will return a BigDecimal of `123`, while `USD 1.2345` will return `123.45`.
     *
     * @return BigDecimal
     */
    public function getMinorAmount()
    {
        return $this->amount->withPointMovedRight($this->currency->getDefaultFractionDigits());
    }

    /**
     * Returns a BigInteger containing the unscaled value of this money in minor units.
     *
     * For example, `123.4567 USD` will return a BigInteger of `1234567`.
     *
     * @return BigInteger
     */
    public function getUnscaledAmount()
    {
        return BigInteger::of($this->amount->unscaledValue());
    }

    /**
     * Returns the Currency of this Money.
     *
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Returns the Context of this Money.
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Converts this Money to a Money with the given Context.
     *
     * @param Context $context
     * @param int     $roundingMode
     *
     * @return Money
     */
    public function with(Context $context, $roundingMode = RoundingMode::UNNECESSARY)
    {
        return self::create($this->amount, $this->currency, $context, $roundingMode);
    }

    /**
     * Returns the sum of this Money and the given amount.
     *
     * The resulting Money has the same context as this Money. If the result needs rounding to fit this context, a
     * rounding mode can be provided. If a rounding mode is not provided and rounding is necessary, an exception is
     * thrown.
     *
     * @param Money|BigNumber|number|string $that         The amount to add.
     * @param int                           $roundingMode An optional RoundingMode constant.
     *
     * @return Money
     *
     * @throws ArithmeticException    If the argument is an invalid number or rounding is necessary.
     * @throws MoneyMismatchException If the argument is a money in a different currency or in a different context.
     */
    public function plus($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $that = $this->handleMoney($that, __FUNCTION__);
        $amount = $this->amount->plus($that);

        return self::create($amount, $this->currency, $this->context, $roundingMode);
    }

    /**
     * Returns the difference of this Money and the given amount.
     *
     * The resulting Money has the same context as this Money. If the result needs rounding to fit this context, a
     * rounding mode can be provided. If a rounding mode is not provided and rounding is necessary, an exception is
     * thrown.
     *
     * @param Money|BigNumber|number|string $that         The amount to subtract.
     * @param int                           $roundingMode An optional RoundingMode constant.
     *
     * @return Money
     *
     * @throws ArithmeticException    If the argument is an invalid number or rounding is necessary.
     * @throws MoneyMismatchException If the argument is a money in a different currency or in a different context.
     */
    public function minus($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $that = $this->handleMoney($that, __FUNCTION__);
        $amount = $this->amount->minus($that);

        return self::create($amount, $this->currency, $this->context, $roundingMode);
    }

    /**
     * Returns the product of this Money and the given number.
     *
     * The resulting Money has the same context as this Money. If the result needs rounding to fit this context, a
     * rounding mode can be provided. If a rounding mode is not provided and rounding is necessary, an exception is
     * thrown.
     *
     * @param BigNumber|number|string $that         The multiplier.
     * @param int                     $roundingMode An optional RoundingMode constant.
     *
     * @return Money
     *
     * @throws ArithmeticException If the argument is an invalid number or rounding is necessary.
     */
    public function multipliedBy($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $amount = $this->amount->multipliedBy($that);

        return self::create($amount, $this->currency, $this->context, $roundingMode);
        }

    /**
     * Returns the result of the division of this Money by the given number.
     *
     * The resulting Money has the same context as this Money. If the result needs rounding to fit this context, a
     * rounding mode can be provided. If a rounding mode is not provided and rounding is necessary, an exception is
     * thrown.
     *
     * @param BigNumber|number|string $that         The divisor.
     * @param int                     $roundingMode An optional RoundingMode constant.
     *
     * @return Money
     *
     * @throws ArithmeticException If the argument is an invalid number or is zero, or rounding is necessary.
     */
    public function dividedBy($that, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $amount = $this->amount->toBigRational()->dividedBy($that);

        return self::create($amount, $this->currency, $this->context, $roundingMode);
    }

    /**
     * Returns the quotient of the division of this Money by the given number.
     *
     * The given number must be a integer value. The resulting Money has the same context as this Money.
     * This method can serve as a basis for a money allocation algorithm.
     *
     * @param BigNumber|number|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @return Money
     *
     * @throws ArithmeticException If the divisor cannot be converted to a BigInteger.
     */
    public function quotient($that)
    {
        $that = BigInteger::of($that);
        $step = $this->context->getStep();

        $scale  = $this->amount->scale();
        $amount = $this->amount->withPointMovedRight($scale)->dividedBy($step);

        $q = $amount->quotient($that);
        $q = $q->multipliedBy($step)->withPointMovedLeft($scale);

        return new Money($q, $this->currency, $this->context);
    }

    /**
     * Returns the quotient and the remainder of the division of this Money by the given number.
     *
     * The given number must be an integer value. The resulting Money has the same context as this Money.
     * This method can serve as a basis for a money allocation algorithm.
     *
     * @param BigNumber|number|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @return Money[] The quotient and the remainder.
     *
     * @throws ArithmeticException If the divisor cannot be converted to a BigInteger.
     */
    public function quotientAndRemainder($that)
    {
        $that = BigInteger::of($that);
        $step = $this->context->getStep();

        $scale  = $this->amount->scale();
        $amount = $this->amount->withPointMovedRight($scale)->dividedBy($step);

        list ($q, $r) = $amount->quotientAndRemainder($that);

        $q = $q->multipliedBy($step)->withPointMovedLeft($scale);
        $r = $r->multipliedBy($step)->withPointMovedLeft($scale);

        $quotient  = new Money($q, $this->currency, $this->context);
        $remainder = new Money($r, $this->currency, $this->context);

        return [$quotient, $remainder];
    }

    /**
     * Allocates this Money according to a list of ratios.
     *
     * For example, `USD 50.00` allocated to [1, 2, 3, 4] would return:
     * [`USD 5.00`, `USD 10.00`, `USD 15.00`, `USD 20.00`].
     *
     * If the allocation yields a remainder, its amount is split evenly over the first monies in the list.
     *
     * The resulting monies have the same context as this Money.
     *
     * @param int[] $ratios
     *
     * @return Money[]
     */
    public function allocate(array $ratios)
    {
        $total = array_sum($ratios);
        $step = $this->context->getStep();

        $monies = [];

        $unit = BigDecimal::ofUnscaledValue($step, $this->amount->scale());
        $unit = new Money($unit, $this->currency, $this->context);

        $remainder = $this;

        foreach ($ratios as $ratio) {
            $money = $this->multipliedBy($ratio)->quotient($total);
            $remainder = $remainder->minus($money);
            $monies[] = $money;
        }

        foreach ($monies as $key => $money) {
            if ($remainder->isZero()) {
                break;
            }

            $monies[$key] = $money->plus($unit);
            $remainder = $remainder->minus($unit);
    }

        return $monies;
    }

    /**
     * Returns a Money whose value is the absolute value of this Money.
     *
     * The resulting Money has the same context as this Money.
     *
     * @return Money
     */
    public function abs()
    {
        return new Money($this->amount->abs(), $this->currency, $this->context);
    }

    /**
     * Returns a Money whose value is the negated value of this Money.
     *
     * The resulting Money has the same context as this Money.
     *
     * @return Money
     */
    public function negated()
    {
        return new Money($this->amount->negated(), $this->currency, $this->context);
    }

    /**
     * Returns the sign of this Money.
     *
     * @return int -1 if the number is negative, 0 if zero, 1 if positive.
     */
    public function getSign()
    {
        return $this->amount->sign();
    }

    /**
     * Returns whether this Money has zero value.
     *
     * @return bool
     */
    public function isZero()
    {
        return $this->amount->isZero();
    }

    /**
     * Returns whether this Money has a negative value.
     *
     * @return bool
     */
    public function isNegative()
    {
        return $this->amount->isNegative();
    }

    /**
     * Returns whether this Money has a negative or zero value.
     *
     * @return bool
     */
    public function isNegativeOrZero()
    {
        return $this->amount->isNegativeOrZero();
    }

    /**
     * Returns whether this Money has a positive value.
     *
     * @return bool
     */
    public function isPositive()
    {
        return $this->amount->isPositive();
    }

    /**
     * Returns whether this Money has a positive or zero value.
     *
     * @return bool
     */
    public function isPositiveOrZero()
    {
        return $this->amount->isPositiveOrZero();
    }

    /**
     * Compares this Money to the given amount.
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return int [-1, 0, 1] if `$this` is less than, equal to, or greater than `$that`.
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function compareTo($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->compareTo($that);
    }

    /**
     * Returns whether this Money is equal to the given amount.
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return bool
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function isEqualTo($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->isEqualTo($that);
    }

    /**
     * Returns whether this Money is less than the given amount
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return bool
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function isLessThan($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->isLessThan($that);
    }

    /**
     * Returns whether this Money is less than or equal to the given amount.
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return bool
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function isLessThanOrEqualTo($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->isLessThanOrEqualTo($that);
    }

    /**
     * Returns whether this Money is greater than the given amount.
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return bool
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function isGreaterThan($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->isGreaterThan($that);
    }

    /**
     * Returns whether this Money is greater than or equal to the given amount.
     *
     * @param Money|BigNumber|number|string $that
     *
     * @return bool
     *
     * @throws ArithmeticException       If the argument is an invalid number.
     * @throws MoneyMismatchException If the argument is a money in a different currency.
     */
    public function isGreaterThanOrEqualTo($that)
    {
        $that = $this->handleMoney($that);

        return $this->amount->isGreaterThanOrEqualTo($that);
    }

    /**
     * Converts this Money to another currency, using an exchange rate.
     *
     * By default, the resulting Money has the same context as this Money.
     * This can be overridden by providing a Context.
     *
     * For example, converting a default money of `USD 1.23` to `EUR` with an exchange rate of `0.91` and
     * RoundingMode::UP will yield `EUR 1.12`.
     *
     * @param Currency|string         $currency     The target currency, as a Currency instance or ISO currency code.
     * @param BigNumber|number|string $exchangeRate   The exchange rate to multiply by.
     * @param Context|null            $context      An optional context.
     * @param int                     $roundingMode An optional rounding mode.
     *
     * @return Money
     *
     * @throws UnknownCurrencyException If an unknown currency code is given.
     * @throws ArithmeticException      If the exchange rate or rounding mode is invalid, or rounding is necessary.
     */
    public function convertedTo($currency, $exchangeRate, Context $context = null, $roundingMode = RoundingMode::UNNECESSARY)
    {
        $currency = Currency::of($currency);

        if ($context === null) {
            $context = $this->context;
        }

        $amount = $this->amount->toBigRational()->multipliedBy($exchangeRate);

        return self::create($amount, $currency, $context, $roundingMode);
    }

    /**
     * Formats this Money with the given NumberFormatter.
     *
     * Note that NumberFormatter internally represents values using floating point arithmetic,
     * so discrepancies can appear when formatting very large monetary values.
     *
     * @param \NumberFormatter $formatter
     *
     * @return string
     */
    public function formatWith(\NumberFormatter $formatter)
    {
        return $formatter->formatCurrency(
            (string) $this->amount,
            (string) $this->currency
        );
    }

    /**
     * Formats this Money to the given locale.
     *
     * Note that this method uses NumberFormatter, which internally represents values using floating point arithmetic,
     * so discrepancies can appear when formatting very large monetary values.
     *
     * @param string $locale
     *
     * @return string
     */
    public function formatTo($locale)
    {
        return $this->formatWith(new \NumberFormatter($locale, \NumberFormatter::CURRENCY));
    }

    /**
     * @return RationalMoney
     */
    public function toRational()
    {
        return new RationalMoney($this->amount->toBigRational(), $this->currency);
    }

    /**
     * Returns a non-localized string representation of this Money, e.g. "EUR 23.00".
     *
     * @return string
     */
    public function __toString()
    {
        return $this->currency . ' ' . $this->amount;
    }

    /**
     * Handles the special case of monies in methods like `plus()`, `minus()`, etc.
     *
     * @param Money|BigNumber|number|string $that   The Money instance or amount.
     * @param string|null                   $method The method name. If provided, checks that the contexts match.
     *
     * @return BigNumber|number|string
     *
     * @throws MoneyMismatchException If monies don't match.
     */
    private function handleMoney($that, $method = null)
    {
        if ($that instanceof Money) {
            if (! $that->currency->is($this->currency)) {
                throw MoneyMismatchException::currencyMismatch($this->currency, $that->currency);
            }

            if ($method !== null && $this->context != $that->context) { // non-strict equality on purpose
                throw MoneyMismatchException::contextMismatch($method);
            }

            return $that->amount;
        }

        return $that;
    }
}
