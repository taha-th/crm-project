<head>
    <title>Checkout</title>
    <script src="https://js.stripe.com/v3/"></script>
</head>

<body>
    <form id="payment-form">
        <div id="payment-element">
            <!-- Elements will create form elements here -->
        </div>
        <button id="submit">Submit</button>
        <div id="error-message">
            <!-- Display error message to your customers here -->
        </div>
    </form>
</body>
<script>
    // Set your publishable key: remember to change this to your live publishable key in production
    // See your keys here: https://dashboard.stripe.com/apikeys
    const stripe = Stripe("<?= $stripe_publishable_key ?>");

    const options = {
        clientSecret: "<?= $client_secret ?>",
        // Fully customizable with appearance API.
        appearance: {
            /*...*/
        },
    };

    // Set up Stripe.js and Elements to use in checkout form, passing the client secret obtained in step 3
    const elements = stripe.elements(options);

    // Create and mount the Payment Element
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');



    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const {
            error
        } = await stripe.confirmSetup({
            //`Elements` instance that was used to create the Payment Element
            elements,
            confirmParams: {
                return_url: '<?= $return_url?>',
            }
        });

        if (error) {
            // This point will only be reached if there is an immediate error when
            // confirming the payment. Show error to your customer (for example, payment
            // details incomplete)
            const messageContainer = document.querySelector('#error-message');
            messageContainer.textContent = error.message;
        } else {
            // Your customer will be redirected to your `return_url`. For some payment
            // methods like iDEAL, your customer will be redirected to an intermediate
            // site first to authorize the payment, then redirected to the `return_url`.
        }
    });
</script>