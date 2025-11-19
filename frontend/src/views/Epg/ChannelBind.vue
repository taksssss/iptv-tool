<template>
  <div class="channel-bind">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>频道绑定 EPG 源</span>
          <el-button type="primary" @click="handleSave" :loading="epgStore.loading">
            保存配置
          </el-button>
        </div>
      </template>
      
      <el-alert
        title="频道绑定说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>可以为特定频道指定使用特定的 EPG 源，未绑定的频道将使用默认 EPG 源。</p>
      </el-alert>

      <div v-if="bindData.length > 0">
        <div v-for="(item, index) in bindData" :key="index" class="bind-item">
          <el-card shadow="hover">
            <el-form label-width="100px">
              <el-form-item label="EPG 源">
                <el-input v-model="item.epg_src" placeholder="EPG 源地址" />
              </el-form-item>
              <el-form-item label="绑定频道">
                <el-input
                  v-model="item.channels"
                  type="textarea"
                  :rows="3"
                  placeholder="频道名称，多个频道用逗号分隔"
                />
              </el-form-item>
              <el-form-item>
                <el-button type="danger" size="small" @click="removeBindItem(index)">
                  删除此绑定
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </div>
      </div>
      
      <el-button type="success" @click="addBindItem" style="margin-top: 20px">
        <el-icon><Plus /></el-icon>
        添加绑定
      </el-button>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { useEpgStore } from '@/stores/epg'

const epgStore = useEpgStore()
const bindData = ref([])

onMounted(async () => {
  try {
    const data = await epgStore.fetchChannelBindEpg()
    bindData.value = Object.entries(data || {}).map(([epg_src, channels]) => ({
      epg_src,
      channels
    }))
  } catch (error) {
    console.error('Failed to fetch channel bind EPG:', error)
  }
})

const addBindItem = () => {
  bindData.value.push({
    epg_src: '',
    channels: ''
  })
}

const removeBindItem = (index) => {
  bindData.value.splice(index, 1)
}

const handleSave = async () => {
  try {
    await epgStore.saveChannelBindEpg(bindData.value)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error('保存失败')
  }
}
</script>

<style scoped>
.channel-bind {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.bind-item {
  margin-bottom: 15px;
}
</style>
