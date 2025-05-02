<template>
  <div class="policy-wrap ptb-100">
    <div class="container">
      <div class="row">
        <div class="col-xl-10 offset-xl-1">
          <div class="policy-content">
            <h2>Privacy Policy & Terms of Service</h2>

            <div class="policy-section">
              <h3>1. Introduction</h3>
              <p>Welcome to NORAH Payment Gateway. This Privacy Policy and Terms of Service governs your use of our payment gateway services, whether accessed in Zimbabwe or internationally.</p>
            </div>

            <div class="policy-section">
              <h3>2. Data Protection and Privacy</h3>
              <ul>
                <li>We comply with international data protection standards including GDPR and local regulations.</li>
                <li>Personal information collected includes: names, contact details, transaction data, and payment information.</li>
                <li>Your data is encrypted and stored securely in accordance with PCI DSS standards.</li>
                <li>We do not store complete card details on our servers.</li>
                <li>Data is only shared with authorized payment processors and regulatory authorities as required by law.</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>3. Payment Processing</h3>
              <ul>
                <li>We support multiple payment methods including but not limited to:
                  <ul>
                    <li>EcoCash</li>
                    <li>InnBucks</li>
                    <li>International card payments</li>
                    <li>Mobile money transfers</li>
                  </ul>
                </li>
                <li>All transactions are processed in real-time with immediate notification.</li>
                <li>Transaction fees and charges are clearly displayed before processing.</li>
                <li>Exchange rates for international transactions are provided by authorized financial institutions.</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>4. Security Measures</h3>
              <ul>
                <li>256-bit SSL encryption for all transactions</li>
                <li>Two-factor authentication for merchant accounts</li>
                <li>Regular security audits and penetration testing</li>
                <li>Fraud detection and prevention systems</li>
                <li>24/7 transaction monitoring</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>5. Merchant Obligations</h3>
              <ul>
                <li>Maintain accurate business and banking information</li>
                <li>Ensure secure implementation of payment gateway APIs</li>
                <li>Comply with anti-money laundering regulations</li>
                <li>Display clear return and refund policies</li>
                <li>Maintain confidentiality of merchant credentials</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>6. Dispute Resolution</h3>
              <ul>
                <li>Clear process for handling customer disputes</li>
                <li>Chargeback management procedures</li>
                <li>48-hour response time for support queries</li>
                <li>Arbitration procedures for merchant disputes</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>7. Compliance</h3>
              <ul>
                <li>Reserve Bank of Zimbabwe regulations</li>
                <li>International payment processing standards</li>
                <li>Anti-money laundering (AML) requirements</li>
                <li>Know Your Customer (KYC) procedures</li>
                <li>PCI DSS compliance</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>8. Service Availability</h3>
              <ul>
                <li>99.9% uptime guarantee</li>
                <li>Scheduled maintenance windows</li>
                <li>Disaster recovery procedures</li>
                <li>Business continuity planning</li>
              </ul>
            </div>

            <div class="policy-section">
              <h3>9. Termination</h3>
              <p>We reserve the right to suspend or terminate services for:
                <ul>
                  <li>Violation of these terms</li>
                  <li>Fraudulent activities</li>
                  <li>Non-compliance with regulations</li>
                  <li>Extended periods of inactivity</li>
                </ul>
              </p>
            </div>

            <div class="policy-section">
              <h3>10. Updates to Terms</h3>
              <p>We may update these terms periodically. Significant changes will be notified via email and/or dashboard notifications. Continued use of our services constitutes acceptance of updated terms.</p>
            </div>

            <div class="policy-section">
              <h3>Contact Information</h3>
              <p>For any questions regarding these terms or our services:</p>
              <ul>
                <li>Email: support@norahpay.com</li>
                <li>Phone: +263 XXX XXXX</li>
                <li>Address: [Your Business Address]</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrivacyPolicy'
}
</script>

<style scoped>
.policy-wrap {
  background: #ffffff;
  padding: 40px 0;
}

.policy-content {
  background: #fff;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.policy-section {
  margin-bottom: 30px;
}

h2 {
  color: #010647;
  margin-bottom: 30px;
  text-align: center;
  font-size: 2.5em;
}

h3 {
  color: #010647;
  margin-bottom: 15px;
  font-size: 1.5em;
}

ul {
  padding-left: 20px;
}

li {
  margin-bottom: 10px;
  line-height: 1.6;
}

p {
  line-height: 1.6;
  margin-bottom: 15px;
}

.policy-section ul li ul {
  margin-top: 10px;
  margin-bottom: 10px;
}
</style>
