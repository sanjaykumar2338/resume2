(function () {
  const config = window.fixResumePaywall || {};
  const pricingUrl = config.pricingUrl || '/pricing/';

  const showAlert = (options = {}) => {
    if (window.Swal) {
      Swal.fire(options);
    } else if (options.text) {
      alert(options.text);
    }
  };

  const requestEmail = async () => {
    if (config.currentUserEmail) {
      return config.currentUserEmail;
    }

    if (!window.Swal) {
      return prompt(config.messages?.promptText || 'Enter your email') || '';
    }

    const result = await Swal.fire({
      title: config.messages?.promptTitle || 'Start your plan',
      input: 'email',
      inputLabel: config.messages?.promptLabel || 'Email',
      inputPlaceholder: 'you@example.com',
      showCancelButton: true,
      confirmButtonText: 'Continue',
    });

    return result.isConfirmed ? result.value : '';
  };

  const createCheckout = async (email, plan = 'primary') => {
    if (!config.checkoutEndpoint) {
      throw new Error('Checkout endpoint missing');
    }

    const response = await fetch(config.checkoutEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, plan }),
    });

    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body?.message || 'Unable to start checkout.');
    }

    return response.json();
  };

  const startCheckout = async (plan = 'primary') => {
    try {
      if (!config.checkoutEndpoint) {
        window.location.href = pricingUrl;
        return;
      }

      try {
        sessionStorage.setItem('rai_return', window.location.href);
      } catch (error) {
        console.warn(error);
      }

      const email = await requestEmail();
      if (!email) {
        return;
      }

      showAlert({
        title: config.messages?.success || 'Redirecting…',
        allowOutsideClick: false,
        didOpen: () => {
          if (window.Swal) {
            Swal.showLoading();
          }
        },
      });

      const session = await createCheckout(email, plan);
      if (session?.url) {
        window.location.href = session.url;
      } else {
        throw new Error('Missing checkout URL.');
      }
    } catch (error) {
      console.error('Checkout error', error);
      showAlert({
        icon: 'error',
        title: config.messages?.error || 'Purchase failed',
        text: error.message || config.messages?.error,
      });
    }
  };

  const redirectToPricing = () => {
    window.location.href = pricingUrl;
  };

  const openBillingPortal = async () => {
    if (!config.portalEndpoint) {
      redirectToPricing();
      return;
    }
    try {
      const response = await fetch(config.portalEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce || '',
        },
        credentials: 'same-origin',
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data?.message || 'Unable to open billing portal.');
      }
      if (data?.url) {
        window.location.href = data.url;
      } else {
        throw new Error('Portal URL missing.');
      }
    } catch (error) {
      console.error(error);
      showAlert({ icon: 'error', title: error.message || 'Unable to open billing portal.' });
    }
  };

  document.addEventListener('click', (event) => {
    const pricingLink = event.target.closest('[data-pricing-link]');
    if (pricingLink) {
      return; // allow default navigation.
    }

    const portalBtn = event.target.closest('#account-portal, [data-portal-trigger]');
    if (portalBtn) {
      event.preventDefault();
      openBillingPortal();
      return;
    }

    const button = event.target.closest('.ra-trial-button');
    if (button) {
      event.preventDefault();
      startCheckout(button.dataset.plan || 'primary');
    }
  });

  window.fixResumePaywall = {
    ...config,
    startCheckout,
    redirectToPricing,
    openBillingPortal,
  };
})();
