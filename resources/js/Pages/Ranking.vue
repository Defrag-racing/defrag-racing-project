<script setup>
    import { Head, router } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { watchEffect, ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        players_ratings: Object
    });

    const physics = ref('all');
    const screenWidth = ref(window.innerWidth);
    const isRotating = ref(false);
    const interval = ref(null);

    const startInterval = () => {
        if (interval.value == null) {
            interval.value = setInterval(updatePage, 30000);
        }
    }

    const stopInterval = () => {
        clearInterval(interval.value);
        interval.value = null;
    }

    const updatePage = () => {
        if (isRotating.value) {
            return;
        }

        isRotating.value = true;

        router.reload()

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const sortByPhysics = (newPhysics) => {
        physics.value = newPhysics

        router.reload({
            data: {
                physics: physics.value,
                page: 1
            }
        })
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }

    watchEffect(() => {
        physics.value = route().params['physics'] ?? 'all';
    });

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);

        startInterval();

        // Log the value of players_ratings prop
        console.log(props.players_ratings);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
        stopInterval();
    });
</script>

<template>
    <div>
        <Head title="Rankings" />

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center">
                <div class="rounded-md p-3 flex-grow bg-grayop-700 flex flex-col">
                    <div class="flex-grow" v-if="screenWidth > 640">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>MDD ID</th>
                                    <th>User ID</th>
                                    <th>Physics</th>
                                    <th>Mode</th>
                                    <th>Player Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ranking in players_ratings.data" :key="ranking.id">
                                    <td>{{ ranking.name }}</td>
                                    <td>{{ ranking.mdd_id }}</td>
                                    <td>{{ ranking.user_id }}</td>
                                    <td>{{ ranking.physics }}</td>
                                    <td>{{ ranking.mode }}</td>
                                    <td>{{ ranking.player_rating }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-center" v-if="players_ratings.total > players_ratings.per_page">
                        <Pagination :last_page="players_ratings.last_page" :current_page="players_ratings.current_page" :link="players_ratings.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
