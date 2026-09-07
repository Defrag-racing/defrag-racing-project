<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import FooterLink from '@/Components/FooterLink.vue';

    const footerPages = usePage().props.footerPages;
</script>

<template>
    <footer class="border-t border-gray-700 bg-gray-900/60 backdrop-blur-sm">
        <div class="max-w-8xl mt-5 pb-5 flex flex-col items-center mx-auto">
            <!-- media bottom -->
            <div class="flex space-x-4 flex-grow sm:flex-grow-0 mx-auto">
                <div class="group p-1 hover:p-0 transition-all duration-[0.2s] ease-out">
                    <a target="_blank" href="https://discord.defrag.racing" class="rounded-full transition-all duration-[0.2s] ease-out flex p-2 group-hover:p-3  bg-indigo-600 scale-100 hover:bg-indigo-700 text-white cursor-pointer">
                        <svg class="w-6 h-6" width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z" fill="#ffffff"/>
                        </svg>
                    </a>
                </div>
                <div class="group p-1 hover:p-0 transition-all duration-[0.2s] ease-out">
                    <a target="_blank" href="https://youtube.com/defraglegends" class="rounded-full transition-all duration-[0.2s] ease-out flex p-2 group-hover:p-3 bg-red-600 hover:bg-red-700 text-white">
                        <svg class="w-6 h-6" width="800px" height="800px" viewBox="0 -3 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="Dribbble-Light-Preview" transform="translate(-300.000000, -7442.000000)" fill="#ffffff">
                                    <g id="icons" transform="translate(56.000000, 160.000000)">
                                        <path d="M251.988432,7291.58588 L251.988432,7285.97425 C253.980638,7286.91168 255.523602,7287.8172 257.348463,7288.79353 C255.843351,7289.62824 253.980638,7290.56468 251.988432,7291.58588 M263.090998,7283.18289 C262.747343,7282.73013 262.161634,7282.37809 261.538073,7282.26141 C259.705243,7281.91336 248.270974,7281.91237 246.439141,7282.26141 C245.939097,7282.35515 245.493839,7282.58153 245.111335,7282.93357 C243.49964,7284.42947 244.004664,7292.45151 244.393145,7293.75096 C244.556505,7294.31342 244.767679,7294.71931 245.033639,7294.98558 C245.376298,7295.33761 245.845463,7295.57995 246.384355,7295.68865 C247.893451,7296.0008 255.668037,7296.17532 261.506198,7295.73552 C262.044094,7295.64178 262.520231,7295.39147 262.895762,7295.02447 C264.385932,7293.53455 264.28433,7285.06174 263.090998,7283.18289" />
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </a>
                </div>
                <div class="group p-1 hover:p-0 transition-all duration-[0.2s] ease-out">
                    <a target="_blank" href="https://twitch.tv/defraglive" class="rounded-full transition-all duration-[0.2s] ease-out flex p-2 group-hover:p-3 bg-purple-600 hover:bg-purple-700 text-white cursor-pointer">
                        <svg class="w-6 h-6" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="Dribbble-Light-Preview" transform="translate(-141.000000, -7399.000000)" fill="#ffffff">
                                    <g id="icons" transform="translate(56.000000, 160.000000)">
                                        <path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" />
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </a>
                </div>
                <div class="group p-1 hover:p-0 transition-all duration-[0.2s] ease-out">
                    <a target="_blank" href="https://github.com/Defrag-racing/defrag-racing-project" class="rounded-full transition-all duration-[0.2s] ease-out flex p-2 group-hover:p-3 bg-white hover:bg-gray-200 text-black cursor-pointer">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 0.297607C5.37258 0.297607 0 5.67019 0 12.2976C0 17.7406 3.43854 22.3587 8.20711 23.9425C8.80539 24.0524 9.02595 23.6755 9.02595 23.3456C9.02595 23.0457 9.01449 22.3458 9.00876 21.3459C5.67164 21.9958 4.96801 19.573 4.96801 19.573C4.43112 18.1722 3.70094 17.7952 3.70094 17.7952C2.67438 17.0723 3.7838 17.0878 3.7838 17.0878C4.91677 17.1685 5.50897 18.2484 5.50897 18.2484C6.51886 20.0027 8.24491 19.4878 8.92355 19.1805C9.03344 18.4606 9.32068 17.968 9.64249 17.6779C7.027 17.3879 4.29242 16.3481 4.29242 11.6811C4.29242 10.3982 4.75383 9.35334 5.50715 8.53344C5.37726 8.24347 4.97479 6.98463 5.62414 5.33138C5.62414 5.33138 6.60403 5.01279 8.99341 6.54413C9.92853 6.2805 10.9331 6.14741 11.9377 6.14273C12.9423 6.14741 13.9469 6.2805 14.883 6.54413C17.2706 5.01279 18.2486 5.33138 18.2486 5.33138C18.9007 6.98463 18.4954 8.24347 18.3646 8.53344C19.1207 9.35334 19.5782 10.3982 19.5782 11.6811C19.5782 16.3625 16.8364 17.3824 14.2137 17.6696C14.6506 18.0445 15.0336 18.7944 15.0336 19.9383C15.0336 21.5661 15.0172 22.9046 15.0172 23.3456C15.0172 23.6782 15.2331 24.0593 15.8412 23.936C20.6037 22.3555 24 17.7393 24 12.2976C24 5.67019 18.6274 0.297607 12 0.297607Z"/>
                        </svg>
                    </a>
                </div>
                <!-- Rules sits with the icons rather than in the page list
                     below, because it is the one link here a new player is
                     expected to have read. -->
                <div class="group p-1 hover:p-0 transition-all duration-[0.2s] ease-out">
                    <Link :href="route('rules')" :title="$t('Rules')" class="rounded-full transition-all duration-[0.2s] ease-out flex p-2 group-hover:p-3 bg-amber-600 hover:bg-amber-700 text-white cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                        </svg>
                    </Link>
                </div>
            </div>
            <div class="flex space-x-4 flex-grow sm:flex-grow-0 mx-auto mt-3">
                <!-- Footer Navigation Menu-->
                <div class="max-w-8xl mx-auto mt-2">
                    <!-- Navigation Links. The validation log is here rather
                         than behind a login because its whole purpose is to be
                         checkable by anyone, including somebody who does not
                         have an account and does not trust us. -->
                    <div class="space-x-8 md:-my-px md:flex pb-1">
                        <FooterLink :href="route('validation-log')" :active="false" :text="$t('Validation log')" />
                        <FooterLink :href="route('wishlist.index')" :active="false" :text="$t('Bugs & Wishlist')" />
                        <FooterLink v-for="page in footerPages || []" :key="page.slug" :href="'/' + page.slug" :active="false" :text="page.title" />
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
