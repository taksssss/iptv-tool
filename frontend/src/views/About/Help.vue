<template>
  <div class="help">
    <el-card>
      <template #header>
        <span>使用说明</span>
      </template>
      
      <div class="help-content">
        <h2>IPTV 工具箱 - 使用说明</h2>
        
        <el-collapse>
          <el-collapse-item title="配置管理" name="1">
            <p>在配置管理页面，您可以：</p>
            <ul>
              <li>设置 EPG 源地址</li>
              <li>配置频道别名</li>
              <li>设置定时任务</li>
              <li>配置数据库连接</li>
            </ul>
          </el-collapse-item>
          
          <el-collapse-item title="EPG 管理" name="2">
            <p>EPG 管理功能包括：</p>
            <ul>
              <li>查看频道列表</li>
              <li>绑定频道到特定 EPG 源</li>
              <li>生成 EPG XML 文件</li>
            </ul>
          </el-collapse-item>
          
          <el-collapse-item title="直播源管理" name="3">
            <p>直播源管理功能：</p>
            <ul>
              <li>配置直播源地址</li>
              <li>测试直播源速度</li>
              <li>管理直播源模板</li>
            </ul>
          </el-collapse-item>
          
          <el-collapse-item title="台标管理" name="4">
            <p>台标管理功能：</p>
            <ul>
              <li>上传频道台标</li>
              <li>管理台标映射关系</li>
            </ul>
          </el-collapse-item>
        </el-collapse>
      </div>
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.help {
  padding: 20px;
}

.help-content {
  padding: 20px;
}

.help-content h2 {
  margin-bottom: 20px;
  color: #303133;
}

.help-content ul {
  margin-left: 20px;
  margin-top: 10px;
}

.help-content li {
  margin: 5px 0;
  color: #606266;
}
</style>
