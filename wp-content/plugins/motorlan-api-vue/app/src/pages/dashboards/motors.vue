<script lang="ts" setup>
import MotorCongratulationsJohn from '@/views/dashboards/motors/MotorCongratulationsJohn.vue'
import MotorEarningReports from '@/views/dashboards/motors/MotorEarningReports.vue'
import MotorExpensesRadialBarCharts from '@/views/dashboards/motors/MotorExpensesRadialBarCharts.vue'
import MotorGeneratedLeads from '@/views/dashboards/motors/MotorGeneratedLeads.vue'
import MotorInvoiceTable from '@/views/dashboards/motors/MotorInvoiceTable.vue'
import MotorOrder from '@/views/dashboards/motors/MotorOrder.vue'
import MotorPopularProducts from '@/views/dashboards/motors/MotorPopularProducts.vue'
import MotorRevenueReport from '@/views/dashboards/motors/MotorRevenueReport.vue'
import MotorStatistics from '@/views/dashboards/motors/MotorStatistics.vue'
import MotorTotalProfitLineCharts from '@/views/dashboards/motors/MotorTotalProfitLineCharts.vue'
import MotorTransactions from '@/views/dashboards/motors/MotorTransactions.vue'
</script>

<template>
  <VRow class="match-height">
    <!-- 👉 Congratulation John -->
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <MotorCongratulationsJohn />
    </VCol>

    <!-- 👉 Motor Transition -->
    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <MotorStatistics class="h-100" />
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
          <MotorTotalProfitLineCharts />
        </VCol>

        <!-- 👉 Expenses Radial Bar Charts -->
        <VCol
          cols="12"
          lg="6"
          md="3"
          sm="6"
        >
          <MotorExpensesRadialBarCharts />
        </VCol>

        <!-- 👉 Generated Leads -->
        <VCol
          cols="12"
          md="6"
          lg="12"
        >
          <MotorGeneratedLeads />
        </VCol>
      </VRow>
    </VCol>

    <!-- 👉 Revenue Report -->
    <VCol
      cols="12"
      lg="8"
    >
      <MotorRevenueReport />
    </VCol>

    <!-- 👉 Earning Reports -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <MotorEarningReports />
    </VCol>

    <!-- 👉 Popular Products -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <MotorPopularProducts />
    </VCol>

    <!-- 👉 Order -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <MotorOrder />
    </VCol>

    <!-- 👉 Transaction -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <MotorTransactions />
    </VCol>

    <!-- 👉 Invoice Table -->
    <VCol
      cols="12"
      lg="8"
    >
      <MotorInvoiceTable />
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/libs/apex-chart.scss";
</style>
