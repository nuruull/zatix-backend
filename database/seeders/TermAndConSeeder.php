<?php

namespace Database\Seeders;

use App\Models\TermAndCon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TermAndConSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermAndCon::insert([
            [
                'content' => '
                    <p><strong>1. Introduction</strong><br>
                    Welcome to EventHub. These Terms and Conditions govern your use of our platform and services as an Event Organizer. By accessing or using our services, you agree to be bound by these Terms.</p>

                    <p><strong>2. Event Creation Process</strong><br>
                    Our platform requires event organizers to go through a verification process before gaining full access to create events. This process includes:</p>

                    <ul>
                        <li>Submitting basic information about your organization and event</li>
                        <li>Scheduling a pitching session with our team</li>
                        <li>Demonstrating your event concept during the pitching session</li>
                        <li>Receiving a demo account to test our platform</li>
                    </ul>
                ',
                'type' => 'event',
            ],
            [
                'content' => '
                    <p><strong>1. Introduction</strong><br>
                    Welcome to ZaTix. These Terms and Conditions govern your use of our platform and services as an Event Organizer. By accessing or using our services, you agree to be bound by these Terms.</p>

                    <p><strong>2. Event Creation Process</strong><br>
                    Our platform requires event organizers to go through a verification process before gaining full access to create events. This process includes:</p>
                    <ul>
                        <li>Submitting basic information about your organization and event</li>
                        <li>Scheduling a pitching session with our team</li>
                        <li>Demonstrating your event concept during the pitching session</li>
                        <li>Receiving a demo account to test our platform</li>
                        <li>Upon satisfaction and approval, receiving full access to our platform</li>
                    </ul>

                    <p><strong>3. Account Usage</strong><br>
                    You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>

                    <p><strong>4. Content Guidelines</strong><br>
                    All events created on our platform must comply with our content guidelines. Events that promote illegal activities, hate speech, or violate any applicable laws are strictly prohibited.</p>

                    <p><strong>5. Fees and Payments</strong><br>
                    Depending on your subscription plan, fees may apply for using our platform. All fees are non-refundable unless otherwise specified in our refund policy.</p>

                    <p><strong>6. Free Account Limitations</strong><br>
                    Free accounts are limited to creating 1 event with a maximum of 10 participants. To create more events or increase participant limits, you will need to upgrade to a paid plan.</p>

                    <p><strong>7. Termination</strong><br>
                    We reserve the right to terminate or suspend your account at any time for violations of these Terms or for any other reason at our sole discretion.</p>

                    <p><strong>8. Limitation of Liability</strong><br>
                    To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenues.</p>

                    <p><strong>9. Changes to Terms</strong><br>
                    We may modify these Terms at any time. Your continued use of our platform after such changes constitutes your acceptance of the new Terms.</p>

                    <p><strong>10. Contact Information</strong><br>
                    If you have any questions about these Terms, please contact us at <a href="mailto:support@zatix.com">support@zatix.com</a>.</p>
                    ',
                'type' => 'general',
            ]
        ]);
    }
}
