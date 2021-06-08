<?php


    declare(strict_types = 1);


    namespace Tests\fixtures\Mail;

    use WPEmerge\Mail\Mailable;

    class DiscountMail extends Mailable
    {

        public $product;

        public function __construct($product)
        {
            $this->product = $product;
        }

        public function build() : Mailable
        {
            return $this
                ->text('new_discount')
                ->subject('We have a new discount');

        }

        public function unique() : bool
        {
        }

    }