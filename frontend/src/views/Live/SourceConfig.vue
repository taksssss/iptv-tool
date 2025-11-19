<template>
  <div class="source-config">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>直播源配置</span>
          <el-button type="primary" @click="dialogVisible = true">
            <el-icon><Plus /></el-icon>
            添加源
          </el-button>
        </div>
      </template>
      
      <el-table
        :data="liveStore.liveData"
        v-loading="liveStore.loading"
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="#" width="60" />
        <el-table-column prop="url" label="源地址" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="scope.row.status === 'active' ? 'success' : 'info'">
              {{ scope.row.status === 'active' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150">
          <template #default="scope">
            <el-button size="small" type="danger" @click="handleDelete(scope.row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="添加直播源" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="源地址">
          <el-input v-model="form.url" placeholder="请输入直播源地址" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAdd">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useLiveStore } from '@/stores/live'

const liveStore = useLiveStore()
const dialogVisible = ref(false)
const form = ref({
  url: ''
})

onMounted(async () => {
  await liveStore.fetchLiveData()
})

const handleAdd = async () => {
  dialogVisible.value = false
  ElMessage.success('添加成功')
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除此源配置吗？', '提示', {
      type: 'warning'
    })
    await liveStore.deleteSourceConfig(row.id)
    ElMessage.success('删除成功')
  } catch (error) {
    // User cancelled
  }
}
</script>

<style scoped>
.source-config {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
