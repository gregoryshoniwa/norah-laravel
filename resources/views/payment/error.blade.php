<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        'primary-dark': '#3730a3',
                    },
                    animation: {
                        'gradient': 'gradient 15s ease infinite',
                        'fade-in': 'fadeIn 0.5s ease-in',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-in': 'bounceIn 0.8s ease-out',
                    },
                    keyframes: {
                        gradient: {
                            '0%, 100%': {
                                'background-size': '200% 200%',
                                'background-position': 'left center'
                            },
                            '50%': {
                                'background-size': '200% 200%',
                                'background-position': 'right center'
                            }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(30px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
                        },
                        bounceIn: {
                            '0%': {
                                transform: 'scale(0.3)',
                                opacity: '0'
                            },
                            '50%': {
                                transform: 'scale(1.05)',
                                opacity: '1'
                            },
                            '70%': {
                                transform: 'scale(0.9)',
                            },
                            '100%': {
                                transform: 'scale(1)',
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .animate-gradient {
            background: linear-gradient(-45deg, #0f172a, #1e293b, #4f46e5, #6366f1);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        .glass-effect {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .error-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-600 animate-gradient">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-1/2 w-80 h-80 bg-indigo-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative min-h-screen py-8 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <!-- Main Result Card -->
        <div class="max-w-md w-full bg-white/90 glass-effect rounded-2xl shadow-2xl overflow-hidden animate-slide-up">

            <!-- Header with Status -->
            <div class="p-6 bg-gradient-to-r from-red-500 to-pink-600 text-white">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 error-icon bg-red-600 rounded-full flex items-center justify-center animate-bounce-in">
                        <!-- Error X -->
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-center text-white mb-2">
                    Payment Error
                </h1>

                <p class="text-center text-white/90 text-sm">
                    An error occurred processing your payment
                </p>
            </div>

            <!-- Error Details -->
            <div class="p-6 space-y-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-red-800">Error Details</h3>
                            <p class="text-sm text-red-700 mt-1">{{ $message }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-red-100 rounded-md">
                    <p class="text-sm text-red-800">
                        <strong>Need Help?</strong> Please contact support or try again later.
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="px-6 pb-6">
                <div class="flex space-x-3">
                    <button
                        onclick="location.href='/'"
                        class="flex-1 bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-dark hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 shadow-lg"
                    >
                        Return Home
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
