<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="brand">{{ setting('store_name', 'Eluxee') }}</div>
                <p>{{ setting('store_tagline', 'Fine jewelry handcrafted in small batches since 1999. Recycled gold, traceable stones, lifetime care.') }}</p>
            </div>
            <div>
                <h4>Shop</h4>
                <ul>
                    @foreach (\App\Models\Category::active()->ordered()->take(5)->get() as $footerCategory)
                        <li><a href="{{ route('shop.index', ['category' => $footerCategory->slug]) }}">{{ $footerCategory->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('shop.index') }}">All jewelry</a></li>
                </ul>
            </div>
            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="{{ route('page.about') }}">Our story</a></li>
                    <li><a href="{{ route('page.offers') }}">Offers &amp; coupons</a></li>
                    <li><a href="{{ route('page.contact') }}">Contact us</a></li>
                    <li><a href="{{ route('account.dashboard') }}">My account</a></li>
                    <li><a href="{{ route('account.orders') }}">Track my order</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    @if (setting('contact_email'))
                        <li><a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a></li>
                    @endif
                    @if (setting('contact_phone'))
                        <li><a href="tel:{{ setting('contact_phone') }}">{{ setting('contact_phone') }}</a></li>
                    @endif
                    @if (setting('contact_address'))
                        <li><span style="font-size:14px">{{ setting('contact_address') }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ setting('store_name', 'Eluxee') }}. All rights reserved.</span>
            <span>Secure payment · Direct bank transfer</span>
        </div>
    </div>
</footer>
