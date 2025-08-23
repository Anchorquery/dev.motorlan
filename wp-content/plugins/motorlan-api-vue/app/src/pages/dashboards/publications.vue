<script lang="ts" setup>
import PublicacionCongratulationsJohn from '@/views/dashboards/publications/PublicacionCongratulationsJohn.vue'
import PublicacionEarningReports from '@/views/dashboards/publications/PublicacionEarningReports.vue'
import PublicacionExpensesRadialBarCharts from '@/views/dashboards/publications/PublicacionExpensesRadialBarCharts.vue'
import PublicacionGeneratedLeads from '@/views/dashboards/publications/PublicacionGeneratedLeads.vue'
import PublicacionInvoiceTable from '@/views/dashboards/publications/PublicacionInvoiceTable.vue'
import PublicacionOrder from '@/views/dashboards/publications/PublicacionOrder.vue'
import PublicacionPopularProducts from '@/views/dashboards/publications/PublicacionPopularProducts.vue'
import PublicacionRevenueReport from '@/views/dashboards/publications/PublicacionRevenueReport.vue'
import PublicacionStatistics from '@/views/dashboards/publications/PublicacionStatistics.vue'
import PublicacionTotalProfitLineCharts from '@/views/dashboards/publications/PublicacionTotalProfitLineCharts.vue'
import PublicacionTransactions from '@/views/dashboards/publications/PublicacionTransactions.vue'
</script>

<template>
  <VRow class="match-height">
    <!-- 👉 Congratulation John -->
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <PublicacionCongratulationsJohn />
    </VCol>

    <!-- 👉 Publicacion Transition -->
    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <PublicacionStatistics class="h-100" />
    </VCol>

    <VCol
      cols="12"
      lg="4"
    >
      <VRow>
        <!-- 👉 Total Profit Line -->
        <VCol
          cols="12"
          lg="6"
          md="3"
          sm="6"
        >
          <PublicacionTotalProfitLineCharts />
        </VCol>

        <!-- 👉 Expenses Radial Bar Charts -->
        <VCol
          cols="12"
          lg="6"
          md="3"
          sm="6"
        >
          <PublicacionExpensesRadialBarCharts />
        </VCol>

        <!-- 👉 Generated Leads -->
        <VCol
          cols="12"
          md="6"
          lg="12"
        >
          <PublicacionGeneratedLeads />
        </VCol>
      </VRow>
    </VCol>

    <!-- 👉 Revenue Report -->
    <VCol
      cols="12"
      lg="8"
    >
      <PublicacionRevenueReport />
    </VCol>

    <!-- 👉 Earning Reports -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <PublicacionEarningReports />
    </VCol>

    <!-- 👉 Popular Products -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <PublicacionPopularProducts />
    </VCol>

    <!-- 👉 Order -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <PublicacionOrder />
    </VCol>

    <!-- 👉 Transaction -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <PublicacionTransactions />
    </VCol>

    <!-- 👉 Invoice Table -->
    <VCol
      cols="12"
      lg="8"
    >
      <PublicacionInvoiceTable />
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/libs/apex-chart.scss";
</style>
